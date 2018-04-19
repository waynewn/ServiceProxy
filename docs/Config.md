#配置文件说明

config下sample目录下保存了配置的模板，使用是需要复制到config/下根据具体情况修改

名词解释

- **XML**：  系统部署配置是用XML格式的文件记录的，关于xml基本概念这里不再赘述
- **module**：  服务模块，http请求路径的根路径（http://1.2.3.4:8080/uc/user/login 中uc就是）
- **center**： 中央管理服务器
- **proxy**： 部署在各个服务器上的代理服务
- **node**: 服务节点，一个微服务实例。一个微服务内可以提供多个服务模块
- **框架的 请求数 和 任务数**: 基于swoole开发，支持fork task进程，即服务端收到一个请求后，可以根据需要fork 若干 task进程，两个分别占用不同的启动配置,参看 src/cetner.php; src/proxy.php; src/Email.php 中

$http->set(array(
    'worker_num' =>$worker_num,// 支持的并发请求数
    'task_worker_num'=>100,//task数量，因为主要是处理报警任务，所以100个足够了
));


## 1. 系统自带的邮件发送微服务

配置文件是 config/MailService.php

可以将 config/sample/MailService.php 复制过来做相应修改:

- 设置最大请求数、任务数
- 邮件服务器地址，账户
- 邮件服务器在系统中的使用的服务器模块名（默认MailService）
- 监听端口号

## 2. 部署配置 

可以将 config/sample/sample.xml 复制到config目录下改名改内容:

（xml中comment属性用作写备忘注释用的）

### 2.1 整个微服务环境配置

		<?xml version='1.0' encoding="utf-8"?>
		<application version="1.0.1" centerIp='center的IP' centerPort='center的port' comment="comment是写的注释">
			<log dir="" comment="日志存放路径，空表示全部记录到php的error_log" />

	        <runtime_ini comment="重启后才能生效">
	            <item name="MICRO_SERVICE_MODULENAME" value="ServiceProxy" comment="如果系统里已经存在名为 ServiceProxy 的项目了，这里改下"/>
	            <item name="EXPIRE_SECONDS_NODE_FAILED" value="300" comment="投递失败的节点，多久后再次尝试"/>
	            <item name="MAX_TASK_NUM" value="100" comment="最大可fork的task任务数"/>
	            <item name="PROXY_TIMEOUT" value="1" comment="proxy代理请求时超时的设置（单位秒）"/>
	            <item name="MAX_REWRITE" value="200" comment="整个系统最多支持多少条rewrite"/>
	            <item name="MAX_SERVICES" value="1000" comment="整个系统最多支持多少个Service"/>
	            <item name="MAX_NODE_PER_SERVICE" value="50" comment="每个Service最多有多少个node实例提供服务"/>
	        </runtime_ini>

- 微服务中间件占用的服务模块名称 
- proxy那里，对于投递失败的node，多少秒后代理请求时再次尝试投递，设置为0表示不延迟等待，下次立即重试
- 最大并发task任务进程数量等等


### 2.2 监控管理配置

提供代码里用到的配置，项目默认提供了一个发邮件的微服务，使用需要用到：

- 微服务URI
- 用户组

可以根据需要自定义新的服务和用户组，相关代码参看在src/ProxyAlert

		<monitor>
            <services >
                <service type='email' uri='/MailService/Broker/sendAsync'/>
            </services>
            <usergroup>
                <group type='ErrorNode' data='wangning@zhangyuelicai.com,zuochenggang@zhangyuelicai.com'/>
            </usergroup>
        </monitor>

### 2.3 uri rewrite

当需要替换指定接口时，这里指定(完整替换)

	    <rewrite>
			<rule from="/aa/bb/c" to="/aa/bb/sayhi"/>
			<rule from="/aa/bb/cc" to="/aa/bb/sayhi_proxy"/>
		</rewrite>

### 2.4 系统部署情况描述

		<servers>
			<server ip="192.168.4.240" proxyport="9002" comment="center要能访问到的地址">
				<nodes comment="该server里部署了的service node">
	                <instance name="mailServ01" templateId="mailServTpl"/>
					<instance name="payment01" templateId="SomeModuleTpl"/>
					<instance name="payment02" templateId="SomeModuleTpl" active="no" port="8011" dir="/app/payment_2" comment="可以覆盖指定端口和发布路径，从而支持一台服务器上部署同一service多个实例"/>
				</nodes>
			</server>
			<server ....>....</server>
		</servers>

这里负责描述清楚部署情况

- server节点标明ip以及该服务器上proxy的监听地址
- 每个服务器上最多只能部署一个proxy（如果该服务器上应用服务不需要通过proxy代理访问其他服务，也不需要远程命令执行能力，可以不部署proxy）
- server下的nodes里列出该服务器上部署的所有的service node，默认active是yes，如果需要加个节点但暂不对外提供服务，这里设置actives为no
- server 里也可以只部署一个proxy,其他什么node都没有

node有很多配置项，这里使用templateId指明是哪个模板，减少冗余，但可以重新指定监听端口和部署路径，从而在一台服务器上部署多个，配置模板参看下一节。

### 2.5 node模板

		<node_templates>
			<template id="SomeModuleTpl" port="8010" dir="/root/ServiceProxy" comment="部署的路径，用于替换命令定义中{dir}">
				<cmds>
	                <stop cmd="{dir}/src/stop.sh"/>
					<start cmd="{dir}/src/start.sh"/>
					<ping cmd="{dir}/src/cmd.sh"/>
				</cmds>
				<serivices>
					<module name='aa'/>
				</serivices>
			</template>
			<template id="mailServTpl" port="8008" dir="/root/ServiceProxy" comment="部署的路径，用于替换命令定义中{dir}">
				<cmds>
	                <stop cmd="{dir}/src/stop.sh"/>
					<start cmd="{dir}/src/start.sh"/>
					<ping cmd="{dir}/src/cmd.sh"/>
				</cmds>
				<serivices>
					<module name='MailService'/>
				</serivices>
			</template>
		</node_templates>

预定义node的模板中指定：

- 默认部署路径，用于拼接3个预定义可通过center调用的命令的路径
- 三个预定义命令，用于通过center启动、停止、健康检查node。运作方式：center向proxy发指令要求执行指定节点的指定命令，proxy找到对应的实际的命令，以 "/root/ServiceProxy/src/stop.sh ip node监听端口" 的格式调用并将结果返回给center
- 说明改node都实现了哪些service module