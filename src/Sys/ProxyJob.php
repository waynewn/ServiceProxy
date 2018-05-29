<?php
namespace Sys;

/**
 * 一次实际的转发请求
 *
 * @author wangning
 */
class ProxyJob {
    public function init($log,$config,$swoole,$counter)
    {
        $this->log = $log;
        $this->config=$config;
        $this->swoole = $swoole;
        $this->counter = $counter;
    }
    public function free()
    {
        $this->log = null;
        $this->config=null;
        $this->swoole = null;
        $this->counter = null;
    }
    protected $swoole;
    protected $counter;
    protected $log;
    /**
     *
     * @var \Sys\Config\ProxyConfig
     */
    protected $config;
    
    public function _proxy($request, $response,$headers = array()){
        $uri = $request->server['request_uri'];
        $post = $request->rawContent();
        $get = $request->get;
        if(empty($post)){
            $post = $request->post;
        }
        $this->_requestWithoutArg = empty($post) && empty($get);
        if(empty($post) && $request->server['request_method']!='GET'){
            $post = '{}';
        }
        $request_time = $request->server['request_time'];
        $this->remoteIP($request);
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
            $this->proxyRequestMd5Flg = md5($this->config->myIp.'#'.$ip_port->ip.'#'.$ip_port->port.'#'.$url.'#'.$request_time.rand(1000000,999999999));
            try{
                $this->log->proxylogFirstTry($this->proxyRequestMd5Flg,$uri, $this->config->myIp, $ip_port->ip, $ip_port->port);
                $this->log->proxylogArgs($this->proxyRequestMd5Flg, array(
                    'Cookie'=>$request->cookie,
                    'QueryString'=>isset($request->server['query_string'])?$request->server['query_string']:'',
                    'Post_or_Raw'=>$post,
                ));
                $dt0 = microtime(true);
                $this->config->proxyStart($ip_port);
                $ret = $this->_proxy2($ip_port->ip==$this->config->myIp?'127.0.0.1':$ip_port->ip, $ip_port->port, $url, $post,$request->cookie,$response,$headers);
                $this->config->proxyEnd($ip_port);
                $this->log->proxylogResult($this->proxyRequestMd5Flg, $uri,$this->config->myIp,$ip_port->ip, $ip_port->port, sprintf('%.2f',(microtime(true)-$dt0)*1000),$ret);
            } catch (\ErrorException $ex) {
                $this->config->proxyEnd($ip_port);
                $again = $this->config->getRouteFor($uri,$request_time,$ip_port->ip.':'.$ip_port->port);

                if($again===null){//没有效路由
                    $this->log->proxylogResult($this->proxyRequestMd5Flg,$uri,$this->config->myIp,$ip_port->ip, $ip_port->port, sprintf('%.2f',(microtime(true)-$dt0)*1000), 'Failed:'.$ex->getMessage());
                    $this->onProxyFaiedAll($response, $uri,$ip_port->ip,$ip_port->port,$request_time);                    
                    return;
                }elseif($again->ip==$ip_port->ip && $again->port==$ip_port->port){//只剩这个路由，没有其他路由，也没必要再试
                    $this->log->proxylogResult($this->proxyRequestMd5Flg,$uri,$this->config->myIp,$ip_port->ip, $ip_port->port, sprintf('%.2f',(microtime(true)-$dt0)*1000), 'Failed:'.$ex->getMessage());
                    $this->onProxyFaiedAll($response, $uri,$ip_port->ip,$ip_port->port,$request_time);                      
                    return;
                }

                $this->onProxyFaiedFirst( $uri,$ip_port->ip,$ip_port->port,$request_time);
                try{
                    $this->log->proxylogTryMore($this->proxyRequestMd5Flg,$uri, $this->config->myIp, $ip_port->ip, $ip_port->port);
                    $dt0 = microtime(true);
                    $this->config->proxyStart($again);
                    $ret = $this->_proxy2($again->ip==$this->config->myIp?'127.0.0.1':$again->ip, $again->port, $url, $post,$request->cookie,$response,$headers);
                    $this->config->proxyEnd($again);
                    $this->log->proxylogResult($this->proxyRequestMd5Flg, $uri,$this->config->myIp,$again->ip, $again->port,sprintf('%.2f',(microtime(true)-$dt0)*1000),$ret);
                } catch (\ErrorException $ex) {
                    $this->config->proxyEnd($again);
                    $this->log->proxylogResult($this->proxyRequestMd5Flg, $uri,$this->config->myIp,$again->ip, $again->port,sprintf('%.2f',(microtime(true)-$dt0)*1000),'Failed:'.$ex->getMessage());
                    $this->onProxyFaiedAll($response, $uri,$again->ip,$again->port,$request_time);
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
    protected function _proxy2($ip,$port,$uriWithQueryString,$args4Post,$cookies,$response,$headers=array())
    {
        $cli = new \Swoole\Coroutine\Http\Client($ip, $port);
        $headers['x-forwarded-for'] = $this->remoteIp;
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

        $ret = $cli->body;
        $statusCode = $cli->statusCode;
        if(!empty($cli->headers['content-type'])){
            $responseType=$cli->headers['content-type'];
        }else{
            $responseType='';
        }
        if(!empty($cli->headers['location'])){
            $relocation =$cli->headers['location'];
        }else{
            $relocation='';
        }
        if(!empty($cli->cookies)){
            foreach($cli->cookies as $k=>$v){
                if(!isset($bakOfCookie[$k]) || $bakOfCookie[$k]!=$v){
                    $response->cookie($k,$v,time()+86400,'/');
                }
            }
	}
        $cli->close();
        if($statusCode==301 || $statusCode==302){
            $response->status($statusCode);
            $response->header("Location", $relocation);
            $this->returnTxtResponse($response, '');
            return '{"relocation":'.$relocation.'}';
        }elseif($statusCode!=200){
            throw new \ErrorException('server down,http-code = '.$statusCode);
        }
        
        if($responseType=='application/json'){
            $this->returnJsonResponse($response, $ret);
        }else{
            $response->header("Content-Type", $responseType);
            $this->returnTxtResponse($response, $ret);
        }
        return $ret;
    }
    
    protected $remoteIp='';
    /**
     * 找出代理IP
     * @param type $request
     * @param type $proxyIP
     * @return type
     */
    protected function remoteIP($request,$proxyIP='100.109')
    {
        //$proxyIP = \Sooh\Base\Ini::getInstance()->get('inner_nat');
        if(!empty($request->header['x-forwarded-for'])){
            return $this->remoteIp = $request->header['x-forwarded-for'];
        }else{
            return $this->remoteIp = $request->server['remote_addr'];
        }
    }
    protected $proxyRequestMd5Flg;
    
    protected function onProxyFaiedFirst($uri,$ip,$port,$request_time)
    {
        $uriWithNodeName = $uri.'(to '.$this->config->getNodename($ip.':'.$port).' sn '.$this->proxyRequestMd5Flg.')';
        $data = array('task'=>'rptErrNode','uri'=>$uriWithNodeName,'ip'=>$ip,'port'=>$port,'time'=>$request_time,);
        $data['prepare4Task']=$this->onErr_prepare4task('rptErrNode',$data);
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
    protected $_requestWithoutArg=false;
    protected function onProxyFaiedAll($response,$uri,$ip,$port,$request_time)
    {
        $tr = new \ErrorException;
        $this->log->trace("[{$this->proxyRequestMd5Flg}]".__FUNCTION__.'()'.$tr->getTraceAsString());

        $uriWithNodeName = $uri.'(to '.$this->config->getNodename($ip.':'.$port).' sn '.$this->proxyRequestMd5Flg.')';
        $data = array('task'=>'rptErrNode','uri'=>$uriWithNodeName.($this->_requestWithoutArg?'[without args]':''),'ip'=>$ip,'port'=>$port,'time'=>$request_time,);
        $data['prepare4Task'] = $this->onErr_prepare4task('rptErrNode',$data);
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
     * 输出结果（json格式）
     * @param type $response
     * @param type $ret
     */
    protected function returnJsonResponse($response,$ret)
    {
        $this->counter->sub(1);
        $response->header("Content-Type", "application/json");
        if(!is_string($ret)){
            $response->end(json_encode($ret));
        }else{
            $response->end($ret);
        }
    }    
    /**
     * 输出结果（txt格式）
     * @param type $response
     * @param type $ret
     */
    protected function returnTxtResponse($response,$ret)
    {
        $this->counter->sub(1);
        $response->end($ret);
    }   
    
    protected function onErr_prepare4task($func,$data)
    {
            if($func=='rptErrNode'){
                    $tmp = $this->config->getRouteFor($this->config->monitorConfig->services['email'],$data['time']);
                    return json_decode(json_encode($tmp),true);
            }else{
                    return null;
            }
    }
}
