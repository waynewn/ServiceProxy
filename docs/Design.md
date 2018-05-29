# 系统设计

作为一个微服务器中间件，需求变更很少，效率要求高，所以没有使用外部依赖，全部基于swoole提供的基础类函数完成。

**文件目录说明**

docs 存放说明文档
src  源码

**名词解释**

- **module**： 服务模块，http请求路径的根路径（http://1.2.3.4:8080/uc/user/login 中uc就是）
- **center**： 中央管理服务器
- **proxy**： 部署在各个服务器上的代理服务
- **node**: 服务节点，一个微服务实例。一个微服务内可以提供多个服务模块

## 1 关键流程说明

### 1.1 center

入口文件是src/center.php，关键逻辑代码是在 src/Sys/CenterXXX类，参看2.5节了解各个类文件主要分工

启动时读取xml配置，转换成一个全局的centerConfig类实例，然后开始监听服务，收到请求后，通过dispatch方法，找到对应的函数执行。

不需要同步立即处理的（目前主要是收到prxoy的上报错误请求这种），使用异步task执行。

经常需要跟多个proxy通讯获取结果，所以这里使用了swoole的协程实现的异步通讯

### 1.2 proxy

启动时根据参数提供的center地址问center要自己的配置设置proxy，本地保存，然后开始监听

收到请求后，通过dispatch方法，找到对应的函数执行。

如果不是系统命令，通过config的getRouter方法（内部是通过loadbalance类）获取路由目标node，发出第一次请求，如果失败，记录，再尝试一次，如果还失败，就返回错误（超时）

失效node最终在负载那里会记录，一段时间内不会再尝试，过了预定义时间后会再次尝试该node。报警目前在ProxyAlert里有段通过邮件微服务发送报警邮件的实现，请酌情修改。

### MicroService 类里记录了taskRunning

taskRunning用于记录当前执行中的任务数量

包含了代理请求任务，但代理请求任务有专门的计数了，所以这个只是参考值。

在获取健康状态时，可以知道当前进行中的任务的数量，对proxy来说，这个数字等于 当前正在转发的任务数量加上正在进行错误报警的任务的数量
所有基于 MicroService的类，默认识别处理 /isThisNodeHealth 和 /shutdownThisNode，举例来说，对于一个proxy, shutdown有两种命令： /ServiceProxy/proxy/shutdown 和 /shutdownThisNode

## 2 代码说明

### 2.1 负载逻辑类

相关文件： src/Sys/LoadBalance/*

负责：

1. 记录请求计数，虽然目前只实现了轮询，但仍记录有最近几分钟内轮询到哪些地址的计数，用于查询状态，确认某节点是否已不再接收请求等等。
2. 记录失败节点，如果要调整投递失败的node，多久后再次尝试的秒数，请在config/ServiceProxy.php中修改

一些细节

1. 初始化时会随机一个起点，不会固定从配置列表里的第一node开始。
2. 发现投递失败的node，下次请求仍会试一次该node，如果还不行，就等一段时间后再试了（时长在config/Consts.php中修改）

### 2.2 配置封装类

相关文件： src/Sys/Config/*

- **XML2CenterConfig**： xml转换成CenterConfig的转换类
- **LogConfig**: 日志配置，诸如路径等（center和proxy使用相同的配置）
- **MonitorConfig**: 监控报警配置，诸如用到的微服务，报警用户分组等
- **CenterConfig**： center的配置文件，center启动的时候会初始化一个，后面每次parse完xml，调用copyFrom函数把相关数据copy过来
- **ProxyConfig**： proxy的配置文件，proxy启动的时候会初始化一个，后面每次变更，使用copyFrom函数copy过来

一些细节：

1. 因系统运行中的配置的实例，可能多进程共用，所以非单一项目的配置，都使用了双方案逻辑：0方案和1方案，由一个变量指针说明当前应该使用哪个方案
2. 代码上，以照顾执行效率为主的封装设计，没有使用内存数据库之类的管理，用了不少数组，请注意其内数组的格式，任何变更请同步更新注释部分。

### 2.3 swoole协程方式发送http请求封装类

相关文件： src/Sys/Coroutione/* 

基本用法：

		$clients = \Sys\Coroutione\Clients::create(超时秒数);
        $clients->addTask(ip, port, /a/b/c?x=1, post参数);
        $ret = $clients->getResultsAndFree(isLastTry);

说明：

1. getResultsAndFree()，如果isLastTry==true，跳过超时设置直接拿结果（当超时处理，强制断连接），如果isLastTry==false，以0.2秒步长不断尝试拿结果，直到超时
2. getResultsAndFree() 返回的结果的中要么是http-code,要么是返回的字符串：

		array(
			'http://$ip:$port/$uriWithQueryString'=>'string',
			'http://$ip:$port/$uriWithQueryString'=>404,
		)


### 2.4 日志记录类

src/Sys/Config/LogConfig: 日志配置，诸如路径等（center和proxy使用相同的配置）

相关文件： src/Sys/Log/*

参看 [日志说明](docs/Logs.md)


### 2.5 center 相关

- **src\Sys\Microservice** 工作于swoole环境的基础相关支持函数
- **src\Sys\CenterAlert**  继承自Microservice，其他业务处理抽离层，主要为了方便改动错误处理逻辑和代理请求数记录逻辑
- **src\Sys\CenterBase** center基类，继承自CenterAlert，实现配置类初始化、基础的起停控制，获取节点状态封装等
- **src\Sys\CenterReport** 中央的管理查询类，继承自CenterBase，主要是管理查询的需求比较多，比较杂，中间插一层方便代码维护
- **src\Sys\Center**  Center最终的类，继承自CenterReport，主要负责运转中相关实现，比如接收处理重载配置文件的命令

### 2.6 proxy 相关

- **src\Sys\Microservice**    工作于swoole环境的基础相关支持函数
- **src\Sys\ServiceLocation** 路由下来目标node的地址
- **src\Sys\ProxyAlert**      错误报告处理抽离层，继承自Microservice，主要为了方便改动
- **src\Sys\Proxy**           proxy类，继承自ProxyAlert，实现代理
- **src\Sys\ProxyJob**        proxy类，一次实际的转发任务的处理函数封装
- **src\proxy**  proxy入口启动文件

### 2.7 其他 

- **src\Sys\Util\Curl**  php curl 函数的封装类 
- **src\Sys\Util\Email\*** 发送邮件的封装类 
- **src\Sys\Util\Funcs** 其他公共函数封装类

## 3 将来可能会完善（只是可能哈）

完善后台管理（尤其是网页版）

更多负载方案

自动扫描，发现宕机node后，自动重启（扫描，下路由，重启，上路由）

