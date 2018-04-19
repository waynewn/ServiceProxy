<?php
namespace Sys;
use \Sys\Util\Funcs as Funcs;
include __DIR__.'/ProxyAlert.php';
include __DIR__.'/Config/ServiceLocation.php';
/**
 * 代理
 * todo: ip限制
 * @author wangning
 */
class Proxy extends ProxyAlert{
    protected $timeStartUp = 0;
    public function __construct($centerUrl) {
        $tmp = $this->getConfig000($centerUrl);
        if($this->isConfigLoadedSuccessfully){
            $this->config=new \Sys\Config\ProxyConfig();
            $this->config->copyFrom($tmp);
//            $this->config->centerIp = $centerIp;
//            $this->config->centerPort = $centerPort;
            $this->log = new \Sys\Log\Txt($this->config->LogConfig, $this->config->myIp);
        }
        $this->timeStartUp = time();
        
    }

    public function onSwooleTask($serv, $task_id, $src_worker_id, $data)
    {
        //todo : 报警
        //array('task'=>'rptErrNode','uri'=>$uri,'ip'=>$ip,'port'=>$port,'time'=>$request_time)
        if($data['task']=='rptErrNode'){
            $this->onErr_ErrorNodeFound($data['uri'], $this->config->myIp, $data['ip'], $data['port'],$data['time'],$data['prepare4Task']);
        }
    }
    
    public function onListenStart()
    {
            $this->log->syslog('proxyStarting: '.$this->config->myIp);
    }
    /**
     *
     * @var \Sys\Log\Txt 
     */
    protected $log = null;
    /**
     * 从中央服务器获取配置文件是否成功
     */
    public $isConfigLoadedSuccessfully=false;

    public function getMyIp()
    {
        return $this->config->myIp;
    }
    public function getMyPort()
    {
        return $this->config->myPort;
    }
    protected function getConfig000($centerUrl){
        $this->isConfigLoadedSuccessfully=false;
        $curl = \Sys\Util\Curl::factory();
        $ret = $curl->httpGet($centerUrl);//'http://'.$ip.':'.$port.'/'.MICRO_SERVICE_MODULENAME.'/center/getProxyConfig'
        if($curl->httpCodeLast!==200 || empty($ret)){
            die('try connect to center and get config failed httpCode='.$curl->httpCodeLast.' response='.$ret);
        }
        $tmp = \Sys\Config\ProxyConfig::factory($ret);
        if(!empty($tmp)){
            $this->isConfigLoadedSuccessfully = true;
            return $tmp;
        }else{
            return null;
        }
    }
    public function dispatch($request,$response)
    {
        $this->log->trace('dispatch '.$request->server['request_uri']);
        switch ($request->server['request_uri']){
            //center通知要更新下服务路由
            case '/'.MICRO_SERVICE_MODULENAME.'/proxy/updateConfig':
                $this->updateConfig($request, $response);
                break;
            //重置center服务器地址,参数有ip,port
            case '/'.MICRO_SERVICE_MODULENAME.'/proxy/resetCenterIP':
                $this->resetCenterIp($request, $response);
                break;            
            //center要本代理节点的状态信息
            case '/'.MICRO_SERVICE_MODULENAME.'/proxy/status':
                $this->status($request, $response);
                break;
            case '/'.MICRO_SERVICE_MODULENAME.'/proxy/nodecmd':
                $this->nodecmd($request, $response);
                break;
            case '/'.MICRO_SERVICE_MODULENAME.'/proxy/dumpconfig':
                $this->returnJsonResponse($response,json_encode($this->config));
                break;
            case '/'.MICRO_SERVICE_MODULENAME.'/proxy/shutdown':
                $this->swoole->shutdown();
                $this->returnJsonResponse($response,array('code'=>0,'msg'=>'shutdown command sent'));
                break;
            case '/'.MICRO_SERVICE_MODULENAME.'/proxy/dumpServiceMap':
                $this->returnJsonResponse($response, array('code'=>0,'serviceMap'=>$this->config->getServiceMap()));
                break;            
            default:
                $this->_proxy($request, $response);
                break;
        }
    }
   
    /**
     * 重置center服务器地址,参数有ip,port
     */
    protected function resetCenterIp($request, $response)
    {
        try{
            $centerIp = Funcs::getIp($this->getReq($request, 'ip'));
            $centerPort = (int)$this->getReq($request, 'port');
            if($centerPort<=0 || $centerPort>65535){
                throw new \ErrorException('invalid port given');
            }
            $this->config->centerIp = $centerIp;
            $this->config->centerPort=$centerPort;
            return $this->returnJsonResponse($response, array('code'=>0,'msg'=>'centerIp changed to :'.$centerIp));
        } catch (\ErrorException $ex){
            $this->returnJsonResponse($response, array('code'=>400,'msg'=>$ex->getMessage()));
        }
        
    }
    /**
     * 中控索要本代理节点的状态信息
     * 返回{
     *      startup:"2017-12-3 12:31:12"，
     *      configVersion:"1.2.34"
     *      nodelist:[//            可以通过参数 skipNodeStatus=1 关闭
     *          nodename=>result-of-ping
     *      ]
     *      proxy:{
     *          total:123, // 共代理了123次请求
     *          detail:{
     *              module1@serverIP1@port:62,  //将module1的请求转发到serverIP1@port 62次
     *              module1@serverIP2@port:61
     *          }，
     *          failed:[//最近20条记录
     *              ["/m/c/a","serverIP2:port","2013-12-2 3:23:12"],
     * 
     *          ]
     *      }
     * }
     */
    protected function status($request,$response)
    {
        $skipNodeStatus = $this->getReq($request, 'skipNodeStatus')-0;
        $this->log->syslog('reportStatusOnProxy: '.$this->config->myIp.' skipNodeStatus='.$skipNodeStatus);
        $ret = array(
            'startup'=>date('Y-m-d H:i:s', $this->timeStartUp),
            'proxyip'=>$this->config->myIp,
            'configVersion'=>$this->config->configVersion,
            'request_time'=>date('Y-m-d H:i:s', $request->server['request_time']),
            'proxy'=>$this->config->loadbalanceStatus(),
        );
        $nodes = array_keys($this->config->nodeList);
        if($skipNodeStatus==0){
            foreach($nodes as $nodename){
                $this->log->trace('start get node:'.$nodename.' status');
                $ret['nodelist'][$nodename]=$this->execNodeCmd($nodename, 'ping');
            }
        }
        $this->log->trace('return :'.var_export($ret,true));
        $this->returnJsonResponse($response, json_encode($ret));
    }
    /**
     * 执行节点命令
     * @param type $request
     * @param type $response
     * @return type
     */
    protected function nodecmd($request, $response)
    {
        $ret = array(
            'nodename'=>$this->getReq($request, 'nodename'),
            'nodecmd'=>$this->getReq($request, 'nodecmd'),
        );
        $this->log->syslog('execCmdOnProxy: '.$this->config->myIp,$ret);
        $ret['cmd-ret'] = $this->execNodeCmd($ret['nodename'], $ret['nodecmd']);
        if($ret['cmd-ret']===null){
            $ret['cmd-ret'] = 'cmd missing';
            return $this->returnJsonResponse($response, array('code'=>404,'data'=>$ret));
        }else{
            return $this->returnJsonResponse($response, array('code'=>0,'data'=>$ret));
        }
    }
    
    protected function execNodeCmd($nodename,$nodecmd)
    {
         if(!isset($this->config->nodeList[$nodename][$nodecmd]) || !is_file($this->config->nodeList[$nodename][$nodecmd]) ){
            return null;
        }
        $args = array(
            $this->config->myIp,
            $this->config->nodeList[$nodename]['_port_'],
        );
        $cmd = $this->config->nodeList[$nodename][$nodecmd];
        $process = new \Swoole\Process(function (\Swoole\Process $worker) use ($cmd,$args) {
            try{
            $worker->exec($cmd,$args);
            }catch(\Exception $ex){
                $this->log->execNodeCmdFailed($this->config->myIp,$cmd,$ex->getMessage());
            }
        }, true); // 需要启用标准输入输出重定向
        $process->start();
        return $process->read();
    }
    
    protected function _proxy($request, $response){
        $uri = $request->server['request_uri'];
        $post = $request->rawContent();
        $get = $request->get;

        if(empty($post)){
            $post = $request->post;
        }
        if(empty($post) && $request->server['request_method']!='GET'){
            $post = '{}';
        }
        $request_time = $request->server['request_time'];
        //$request_time = $request->server['request_time_float'];//浮点数，毫秒
        $this->log->trace("enter_proxy with $uri ");
        $ip_port = $this->config->getRouteFor($uri,$request_time);
        
        $this->log->trace("final route:". json_encode($ip_port));
        if(empty($ip_port)){//没找到路由
            $this->onRouteMissing($response, $uri);
        }else{
            if(!empty($get)){
                $url = $ip_port->cmd.'?'. http_build_query($get);
            }else{
                $url = $ip_port->cmd;
            }
            $proxyRequestMd5Flg = md5($this->config->myIp.'#'.$ip_port->ip.'#'.$ip_port->port.'#'.$url.'#'.$request_time.rand(1000000,999999999));
            try{
                $this->log->proxylogFirstTry($proxyRequestMd5Flg,$uri, $this->config->myIp, $ip_port->ip, $ip_port->port);
                $this->log->proxylogArgs($proxyRequestMd5Flg, array(
                    'Cookie'=>$request->cookie,
                    'QueryString'=>isset($request->server['query_string'])?$request->server['query_string']:'',
                    'Post_or_Raw'=>$post,
                ));
                $dt0 = microtime();
                $ret = $this->_proxy2($ip_port->ip==$this->config->myIp?'127.0.0.1':$ip_port->ip, $ip_port->port, $url, $post,$request->cookie,$response);
                $this->log->proxylogResult($proxyRequestMd5Flg, $uri,$this->config->myIp,$ip_port->ip, $ip_port->port, sprintf('%.2f',(microtime()-$dt0)*1000),$ret);
            } catch (\ErrorException $ex) {
                $again = $this->config->getRouteFor($uri,$request_time);
                if($again->ip!=$ip_port->ip && $again->port!=$ip_port->port){
                    $this->onProxyFaiedFirst( $uri,$ip_port->ip,$ip_port->port,$request_time);
                    try{
                        $this->log->proxylogTryMore($proxyRequestMd5Flg,$uri, $this->config->myIp, $ip_port->ip, $ip_port->port);
                        $dt0 = microtime();
                        $ret = $this->_proxy2($again->ip==$this->config->myIp?'127.0.0.1':$again->ip, $again->port, $url, $post,$request->cookie,$response);
                        $this->log->proxylogResult($proxyRequestMd5Flg, $uri,$this->config->myIp,$again->ip, $again->port,sprintf('%.2f',(microtime()-$dt0)*1000),$ret);
                    } catch (\ErrorException $ex) {
                        $this->log->proxylogResult($proxyRequestMd5Flg, $uri,$this->config->myIp,$again->ip, $again->port,sprintf('%.2f',(microtime()-$dt0)*1000),'Failed:'.$ex->getMessage());
                        $this->onProxyFaiedAll($response, $uri,$again->ip,$again->port,$request_time);
                    }
                }else{
                    $this->log->proxylogResult($proxyRequestMd5Flg,$uri,$this->config->myIp,$ip_port->ip, $ip_port->port, sprintf('%.2f',(microtime()-$dt0)*1000), 'Failed:'.$ex->getMessage());
                    $this->onProxyFaiedAll($response, $uri,$ip_port->ip,$ip_port->port,$request_time);
                }
            }
        }
    }
    
    /**
     * 
     * @param type $ip
     * @param type $port
     * @param type $uriWithQueryString 注意带上get参数
     * @param mixed $args4Post 字符串用rawdata，数组用x-www-form-urlencoded 方式进行post
     * @param array $cookies 接收到的需要转发的请求带的cookie
     * @param response $response response
     * @return type
     */
    protected function _proxy2($ip,$port,$uriWithQueryString,$args4Post,$cookies,$response)
    {
        $this->log->trace("$ip:$port/$uriWithQueryString");
        $cli = new \Swoole\Coroutine\Http\Client($ip, $port);
        $headers = array(
//            'Host' => "localhost",
//            "User-Agent" => 'Chrome/49.0.2587.3',
//            'Accept' => 'text/html,application/xhtml+xml,application/xml',
//            'Accept-Encoding' => 'gzip',
        );
        $bakOfCookie=array();
        if(is_array($cookies)){
            foreach($cookies as $k=>$v){
                $bakOfCookie[$k]=$v;
            }
            $cli->setCookies($cookies);
        }
        $cli->set([ 'timeout' => PROXY_TIMEOUT]);//1秒超时
        if(!empty($args4Post)){
            if(!is_array($args4Post)){
                $headers["Content-Type"]="application/json";
            }
            $cli->setHeaders($headers);

            $cli->post($uriWithQueryString,$args4Post);
        }else{
            $cli->setHeaders($headers);

            $cli->get($uriWithQueryString);
        }
        if($cli->statusCode!=200){
            throw new \ErrorException('server down,http-code = '.$cli->statusCode);
        }
        $ret = $cli->body;
        $responseType=$cli->headers['content-type'];
        if(!empty($cli->cookies)){
            foreach($cli->cookies as $k=>$v){
                if(!isset($bakOfCookie[$k]) || $bakOfCookie[$k]!=$v){
                    $response->cookie($k,$v,time()+86400,'/');
                }
            }
		}
        $cli->close();
        
        if($responseType=='application/json'){
            $this->returnJsonResponse($response, $ret);
        }else{
            $this->returnTxtResponse($response, $ret);
        }
        return $ret;
    }
    protected function onProxyFaiedFirst($uri,$ip,$port,$request_time)
    {
        $uriWithNodeName = $uri.'('.$this->config->nodename[$ip.':'.$port].')';
        $data = array('task'=>'rptErrNode','uri'=>$uriWithNodeName,'ip'=>$ip,'port'=>$port,'time'=>$request_time, 'prepare4Task'=>$this->onErr_prepare4task('rptErrNode'));
        try{
            if($this->swoole->task($data)===false){
                $this->log->taskCreateFailed($data,'task pool is full');
            }
        }catch(\ErrorException $ex){
            $this->log->taskCreateFailed($data, $ex->getMessage());
        }
        $this->config->markNodeDown($ip, $port, $request_time);
        $this->log->errorNode($uriWithNodeName, $this->config->myIp, $ip, $port);
    }
    protected function onProxyFaiedAll($response,$uri,$ip,$port,$request_time)
    {
        $uriWithNodeName = $uri.'('.$this->config->nodename[$ip.':'.$port].')';
        $data = array('task'=>'rptErrNode','uri'=>$uriWithNodeName,'ip'=>$ip,'port'=>$port,'time'=>$request_time,'prepare4Task'=>$this->onErr_prepare4task('rptErrNode'));
        try{
            if($this->swoole->task($data)===false){
                $this->log->taskCreateFailed($data,'task pool is full');
            }
        }catch(\ErrorException $ex){
            $this->log->taskCreateFailed($data, $ex->getMessage());
        }
        $this->config->markNodeDown($ip, $port, $request_time);
        $this->log->errorNode($uriWithNodeName, $this->config->myIp, $ip, $port);
        $response->status(503);
    }
    protected function onRouteMissing($response,$uri)
    {
        $this->log->errorNode($uri, null, null, null);
        $response->status(404);
    }
    /**
     * 中控通知要更改配置
     */
    protected function updateConfig($request,$response)
    {
        $this->log->syslog('updateConfigOnProxy: '.$this->config->myIp);
        //$proxyStr = $this->config->proxy[$request->server['remote_addr']];
        $r = json_decode($request->rawContent(),true);
        if(is_array($r) && $r['code']==0){
            $o = \Sys\Config\ProxyConfig::factory($r['data']);
            if(!empty($o)){
                $this->config->copyFrom($o);
                $this->log->trace('"config updated on '.$this->config->myIp .'"');
                $this->returnJsonResponse($response, '{"code":0,"msg":"config updated to '.$this->config->configVersion.'"}');
            }else{
                $this->log->trace("config update failed on '.$this->config->myIp.'");
                $this->returnJsonResponse($response, '{"code":-1,"msg":"data error"}');
            }
        }else{
            $this->log->trace("config update failed on '.$this->config->myIp.'");
            $this->returnJsonResponse($response, '{"code":-1,"msg":"arg error"}');
        }
    }
    

}
