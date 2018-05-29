# 管理维护的操作手册

## 1 总体说明


- **module**：  服务模块，http请求路径的根路径（http://1.2.3.4:8080/uc/user/login 中uc就是）
- **center**： 中央管理服务器
- **proxy**： 部署在各个服务器上的代理服务
- **node**: 服务节点，一个微服务实例。一个微服务内可以提供多个服务模块

配置说明参考 [配置说明](docs/Config.md)


除启动停止，其他命令都是通过curl访问的方式调用的，建议配合jq命令格式化输出

`curl -s 'http://127.0.0.1:80/a/b/c' | jq '.'`

本文后续篇幅，为了简化清晰，只写了curl表示是curl方式执行。

环境安装参看 [环境安装说明](docs/EnvInstall.md)

## 2 流程

### 2.1 部署center

1. 部署服务器，环境安装参看 [环境安装说明](docs/EnvInstall.md)
2. 部署ServiceProxy，
3. 编写xml配置，参考 [配置说明](docs/Config.md)
4. 启动center （参考 3.7节）

### 2.2 部署proxy（有或没有service node）

1. 部署服务器，环境安装参看 [环境安装说明](docs/EnvInstall.md)
2. 部署ServiceProxy
3. 启动proxy （参考 3.8节）
4. 如果该服务器上有service node实例，后面可以启动了（可以手动启动，也可以参考 3.1节通过center发起）

### 2.3 更新service node实例

1. 关闭路由 (参看 3.12节)
2. 等一会，可以通过命令确认没有继续发请求了 (参看 3.10节)
3. 停止node 
4. 更新代码  
5. 启动node
6. 回复路由 （参看 3.13节）

### 2.4 添加service node实例

1. 部署service node项目，并启动
2. 改xml配置
3. center重新加载配置（参考 3.3节）
4. 广播通知所有proxy（参考3.4节）

### 2.5 删除service node实例

1. 更改xml配置文件，
2. center重新加载配置（参考 3.3节）
3. 广播通知所有proxy（参考3.4节）
4. 等没有请求到该service node实例后（多等一段时间），手动停止该服务


### 2.6 替换center

主要发生于原center无法访问了（ip释放了，申请不回来了）

1. 参考2.1部署一台新的center
2. 手动向每个proxy发送重置centerIp命令（参看3.2节）
3. 按理说应该使用备份的配置文件恢复的，如果有变化，再参看变更流程，然后广播更新后的配置（参看3.4节）

### 2.7 center 重启

目前只有proxy启动时需要跟center通讯，所以其他时间center可以直接重启（只影响本地记录各个节点累计代理任务次数归集）。

### 2.7 proxy 重启

1. 关闭该proxy下所有node（由该proxy负责代理请求的node）的路由 (参看 3.12节)
2. 等一会，可以通过命令确认没有继续发请求了 (参看 3.10节)
3. 等一会，确保本地node通过该proxy代理的请求也都结束且没有新请求（参看 3.9节）
4. 重启proxy
5. 回复该proxy下所有node路由 （参看 3.13节）

## 3 命令一览

### 3.1 指定node执行指定命令

`curl "http://{centerIP}:{centerPort}/ServiceProxy/center/nodecmd?node={serviceNodeName}&cmd={start|stop|ping}"`

以 "/path/shellScript ip node监听端口" 的格式调用预定义命令

### 3.2 重置Proxy的center指向

当原center故障无法恢复时，需要重置所有Proxy，将center指向新的地址

`curl "http://{proxyIp}:{proxyPort}/ServiceProxy/center/resetCenterIP?ip={newCenterIP}&port={newCenterPort}"`

### 3.3 center重新加载配置文件

`curl "http://{centerIP}:{centerPort}/ServiceProxy/center/reloadConfig"`

### 3.4 向所有proxy广播配置更新

`curl "http://{centerIP}:{centerPort}/ServiceProxy/center/broadcast"`

### 3.5 shutdown

center

`curl "http://{centerIP}:{centerPort}/ServiceProxy/center/shutdown"`

proxy

`curl "http://{proxyIp}:{proxyPort}/ServiceProxy/proxy/shutdown"`

- 关闭proxy前先确认需要该proxy转发请求的node都已停止
- 因 proxy 和 center 在运行中没有强依赖（目前只是proxy发现路由失败时尝试上报），所以如果发现僵死进程，可直接 kill -9

### 3.6 检查配置文件

`php center.php -t 配置文件名（带相对路径或绝对路径）`

### 3.7 启动center

`php center.php -c 配置文件名（带相对路径或绝对路径） -worker 进程数 &`

中央服务器只对proxy监控管理和后台界面提供服务，所以需要的进程数不多，建议取值：2倍proxy数量+10

### 3.8 启动proxy

`php proxy.php -center 'http://centerip:centerport/ServiceProxy/center/getProxyConfig' -worker 进程数 &`

proxy 需要代理请求，在结果返回之前，连接需要保持，所以进程数取决于 服务数量和服务器数量（如果一个完整业务请求涉及5个服务，又都在一台机器上，一次请求proxy就需要10个进程，在加上并发负载），取值参考： 最大复杂服务器数量 * 2 * 半秒内并发数 / proxy数量

### 3.9 获取proxy（指定或全部）状态

可以通过ips可选参数（逗号分割的ip列表）获取指定proxy的状态

`curl "http://{centerIP}:{centerPort}/ServiceProxy/center/proxisStatus"`

`curl "http://{centerIP}:{centerPort}/ServiceProxy/center/proxisStatus?ips=127.0.0.1,127.0.0.2"`

返回结果

	{
		"code":200,
		"proxiesStatus":{
			"192.168.4.240:9002":{
				"startup":"2018-03-05 12:08:07",
				"configVersion":"1.0.1",
				"proxy":{
					"counter":{
						"03-05 12:08":1,
						"03-05 12:09":10
					},
					"error":[
						{错误信息...}
					]
				}
			},
			"192.168.4.241:9002":{
			......
			}
		}
	}

proxy counter 是当前正在代理请求的任务数

### 3.10 获取路由情况统计(当前各个节点统计下来节点当前正在执行的任务数)

可以通过namelike可选参数，指定那些节点

`curl 'http://127.0.0.1:9001/ServiceProxy/center/routeSummary'`

`curl 'http://127.0.0.1:9001/ServiceProxy/center/routeSummary?namelike=pay'`

返回结果

	{
		"code":200,
		"routeSummary":{
			"03-05 12:08":{
				"nodename1":1,
				"nodename2":1
			},
			"03-05 12:09":{
				"nodename1":1
			}
		}
	}

### 3.11 获取路由配置（ServiceMap）

center

`curl 'http://centerIp:centerPort/ServiceProxy/center/dumpServiceMap'`

proxy

`curl 'http://proxyIp:proxyPort/ServiceProxy/proxy/dumpServiceMap'`

### 3.12 临时停用node

`curl 'http://centerIp:centerPort/ServiceProxy/center/nodeDeactive?nodes=xxx,yyy'`

nodes参数要求提供英文逗号分割的node名称列表

### 3.13 恢复停用的node

`curl 'http://centerIp:centerPort/ServiceProxy/center/nodeActive?nodes=xxx,yyy'`

nodes参数要求提供英文逗号分割的node名称列表

### 3.14 获取最近一段时间，各个proxy总代理请求数

`curl 'http://centerIp:centerPort/ServiceProxy/center/proxisCounter'`

### 3.15 启动邮件发送的微服务

`php src/Email.php &`

`php src/Email.php 监听端口 &`

`php src/Email.php -p 监听端口 &`

`php src/Email.php 监听地址（暂时忽略，保留兼容）   监听端口 &`

运行配置参看 [配置说明](docs/Config.md)

start，stop，ping的脚本请根据情况自行编写，下面给出个ping的参考

		#!/bin/bash
		URL="http://127.0.0.1:$2/MailService/Broker/ping"
		curl -s $URL

### 3.11 打印proxy配置（dumpconfig）

proxy

`curl 'http://proxyIp:proxyPort/ServiceProxy/proxy/dumpconfig'`