#日志说明

-如果xml配置文件中没有配置路径，所有日志记录到php的error_log中，前缀标识了是center还是那个proxy(IP)，以及是那种类型的日志；
-设置了路径的话，在路径下，以center或ProxyIP开头，每个类型的日志每天一个文件

日志类型说明：

- swoole swoole框架的日志文件（这个不出错量很小，不会按天分割日志，mv或unlink之后要向Server发送SIGRTMIN信号实现重新打开日志文件，否则新的信息将无法正常写入）
- trace 调试跟踪类日志
- sys  系统运行日志（比如center向prxoy发了个命令，两边各自记录一下）
- err  代理转发错误日志
- proxy 代理请求的记录，包括时间，参数，耗时（毫秒），返回值，记录较多，所以有个请求的md5签名用于关连多行的记录 

		[08-Mar-2018 12:59:34 Asia/Shanghai] 192.168.4.240【proxy】7dd46ba68f323f3dafd7a482fd4387ee firstTry 192.168.4.240 -> 192.168.4.240:8010//aa/bb/sayhi
		[08-Mar-2018 12:59:34 Asia/Shanghai] 192.168.4.240【proxy】7dd46ba68f323f3dafd7a482fd4387ee Cookie = {"cookiebycurl":"2"}
		[08-Mar-2018 12:59:34 Asia/Shanghai] 192.168.4.240【proxy】7dd46ba68f323f3dafd7a482fd4387ee QueryString = name=qqByProxy
		[08-Mar-2018 12:59:34 Asia/Shanghai] 192.168.4.240【proxy】7dd46ba68f323f3dafd7a482fd4387ee Post_or_Raw = null
		[08-Mar-2018 12:59:34 Asia/Shanghai] 192.168.4.240【proxy】7dd46ba68f323f3dafd7a482fd4387ee 192.168.4.240 -> 192.168.4.240:8010//aa/bb/sayhi CONSUMING_TIME = 0.46
		[08-Mar-2018 12:59:34 Asia/Shanghai] 192.168.4.240【proxy】7dd46ba68f323f3dafd7a482fd4387ee RESULT = {"code":0,"ret":"hi,qqByProxy"}


		[08-Mar-2018 11:58:02 Asia/Shanghai] CENTER【sys】center starting... 
		[08-Mar-2018 11:58:04 Asia/Shanghai] CENTER【trace】Sys\Center->dispatch(): dispatch /ServiceProxy/center/getProxyConfig
		[08-Mar-2018 11:58:04 Asia/Shanghai] CENTER【sys】getProxyConfig {"proxyip":"192.168.4.240"}
		[08-Mar-2018 11:58:04 Asia/Shanghai] 192.168.4.240【sys】proxyStarting: 192.168.4.240 
		[08-Mar-2018 11:58:13 Asia/Shanghai] CENTER【trace】Sys\Center->dispatch(): dispatch /ServiceProxy/center/nodecmd


