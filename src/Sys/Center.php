<?php
namespace Sys;
include __DIR__.'/Config/ProxyConfig.php';
include __DIR__.'/Config/CenterConfig.php';
include __DIR__.'/Config/MonitorConfig.php';
include __DIR__.'/Config/LogConfig.php';
include __DIR__.'/CenterReport.php';

/**
 * 中央控制
 * todo ip限制
 * @author wangning
 */
class Center extends CenterReport{

    public function dispatch($request,$response)
    {
        $remoteAddr = $request->server['remote_addr'];
        if($remoteAddr!=$this->config->centerIp && $remoteAddr!='127.0.0.1' && !isset($this->config->proxy[$remoteAddr])){
            $response->status(404);
            return;
        }
        $this->log->managelog('dispatch '.$request->server['request_uri'].'?'.json_encode($request->get));        
        if(parent::dispatch($request, $response)==true){
            return;
        }

        if($this->dispatchReports($request, $response)==true){
            return;
        }
        switch ($request->server['request_uri']){
            case '/'.MICRO_SERVICE_MODULENAME.'/center/startTimer':
                error_log('WNWN:onListenStart....................2');
                if(defined('CENTER_TIMER_MINUTE') && CENTER_TIMER_MINUTE>0){
                    swoole_timer_tick(CENTER_TIMER_MINUTE*60*1000,array($this,'onTimer'),null);
                }
                
                break;            
            //proxy 来要自己的配置文件
            case '/'.MICRO_SERVICE_MODULENAME.'/center/getProxyConfig':
                $this->getProxyConfig($request, $response);
                break;
            case '/'.MICRO_SERVICE_MODULENAME.'/center/noticeFromProxy':
                $data = array(
                        'task'=>$this->getReq($request, 'task'),
                        'uri'=>$this->getReq($request, 'uri'),
                        'ip'=>$this->getReq($request, 'ip'),
                        'port'=>$this->getReq($request, 'port'),
                        'time'=>$this->getReq($request, 'time'),
                        'proxyIp'=>$request->server['remote_addr'],
                        );
                try{
                    if($this->swoole->task($data)===false){
                        $this->log->taskCreateFailed($data,'task pool is full');
                    }
                } catch (\ErrorException $ex){
                    $this->log->taskCreateFailed($data,$ex->getMessage());
                }
                $this->returnJsonResponse($response, array('code'=>0,'msg'=>'accepted'));
                break;
            
            case '/'.MICRO_SERVICE_MODULENAME.'/center/reloadConfig':
                $this->reloadConfig();
                $this->returnJsonResponse($response,array('code'=>0,'msg'=>'new config version is: '.$this->config->configVersion));
                break;
            case '/'.MICRO_SERVICE_MODULENAME.'/center/broadcast':
                $this->broadcast($request, $response);
                break;
            case '/'.MICRO_SERVICE_MODULENAME.'/center/nodecmd'://node=xx&cmd=yy
                $this->nodecmd($request, $response);
                break;
            case '/'.MICRO_SERVICE_MODULENAME.'/center/shutdown':
                $this->nodeShutdown($response);
                break;
            
            case '/'.MICRO_SERVICE_MODULENAME.'/center/nodeDeactive'://nodes=xx,yy
                $this->nodeDeactive($request, $response);
                break;
            case '/'.MICRO_SERVICE_MODULENAME.'/center/nodeActive'://nodes=xx,yy
                $this->nodeActive($request, $response);
                break;
            case '/'.MICRO_SERVICE_MODULENAME.'/center/dumpServiceMap':
                $this->dumpServiceMap($request, $response);
                break;
        }
    }
    protected function dumpServiceMap($request, $response)
    {
        return $this->returnJsonResponse($response, array('code'=>0,'serviceMap'=>$this->config->getServiceMap(),'deactived'=>$this->config->serviceMapDeactive));
    }
    /**
     * 临时关闭某(些)节点,并广播到各个proxy (这里没考虑两个管理员同时操作，一个关闭一个开启，会冲突)
     * 参数： nodes： 逗号分割的节点名称
     */
    protected function nodeDeactive($request, $response)
    {
        $nodes0 = $this->getReq($request, 'nodes');
        if(empty($nodes0)){
            return $this->returnJsonResponse($response, array('code'=>400,'err'=>'at least one node needs given'));
        }
        $nodes = explode(',',$nodes0);
        $nodeIpPort = array();
        foreach($nodes as $nd){
            if(isset($this->config->nodeLocation[$nd])){
                $r = $this->config->nodeLocation[$nd];
                $nodeIpPort[$r['ip'].':'.$r['port']] = $r;
            }else{
                return $this->returnJsonResponse($response, array('code'=>400,'err'=>'node '.$nd.' not found'));
            }
        }
        
        $oldServiceMap = $this->config->getServiceMap();
        
        foreach($oldServiceMap as $serviceName=>$rs){
            $tmp = array();
            foreach ($rs as $r){
                $s = $r['ip'].':'.$r['port'];
                if(isset($nodeIpPort[$s])){
                    $this->config->serviceMapDeactive[$serviceName][]=$r;
                }else{
                    $tmp[]=$r;
                }
            }
            $oldServiceMap[$serviceName]=$tmp;
        }
        $this->config->setServiceMap($oldServiceMap);
        
        return $this->broadcast($request, $response);
    }
    /**
     * 恢复某(些)节点,并广播到各个proxy(这里没考虑两个管理员同时操作，一个关闭一个开启，会冲突)
     * 参数： nodes： 逗号分割的节点名称
     */
    protected function nodeActive($request, $response)
    {
        $nodes0 = $this->getReq($request, 'nodes');
        if(empty($nodes0)){
            return $this->returnJsonResponse($response, array('code'=>400,'err'=>'at least one node needs given'));
        }
        $nodes = explode(',',$nodes0);
        $nodeIpPort = array();
        foreach($nodes as $nd){
            if(isset($this->config->nodeLocation[$nd])){
                $r = $this->config->nodeLocation[$nd];
                $nodeIpPort[$r['ip'].':'.$r['port']] = $r;
            }else{
                return $this->returnJsonResponse($response, array('code'=>400,'err'=>'node '.$nd.' not found'));
            }
        }
        $oldServiceMap = $this->config->getServiceMap();
        foreach($this->config->serviceMapDeactive as $serviceName=>$rs){
            foreach ($rs as $k=>$r){
                $s = $r['ip'].':'.$r['port'];
                if(isset($nodeIpPort[$s])){
                    $oldServiceMap[$serviceName][]=$r;
                    unset($this->config->serviceMapDeactive[$serviceName][$k]);
                }
            }
        }
        $this->config->setServiceMap($oldServiceMap);
        return $this->broadcast($request, $response);
    }


    /**
     * 执行node命令
     * @return type
     */
    protected function nodecmd($request, $response)
    {
        $cmd = array(
            'nodename'=>$this->getReq($request, 'node'),
            'nodecmd'=>$this->getReq($request, 'cmd'),
        );
        if(!isset($this->config->nodeLocation[$cmd['nodename']])){
            return $this->returnJsonResponse($response, array('code'=>404,'msg'=>'node with name:'.$cmd['nodename'].' not found'));
        }else{
            $proxyIp = $this->config->nodeLocation[$cmd['nodename']]['ip'];
            $cmd['nodeip']=$proxyIp;
            $cmd['nodeport']=$this->config->nodeLocation[$cmd['nodename']]['port'];
            $tmp = \Sys\Config\ProxyConfig::factory($this->config->proxy[$proxyIp]);
            $proxyPort = $tmp->myPort;
        }
        //$this->log->trace("============>$proxyIp:$proxyPort {$cmd['nodename']} {$cmd['nodecmd']}");
        $clients = \Sys\Coroutione\Clients::create(120);
        $clients->addTask($proxyIp, $proxyPort, '/'.MICRO_SERVICE_MODULENAME.'/proxy/nodecmd?'. http_build_query($cmd), null);
        $ret = current($clients->getResultsAndFree());
        //todo: 根据命令，决定配置更新：启动停止
        $this->returnJsonResponse($response, $ret);
    }

    /**
     * 通知所有的proxy 节点更新配置
     * @param type $request
     * @param type $response
     */
    protected function broadcast($request, $response)
    {
        $clients = \Sys\Coroutione\Clients::create(5);
        $ret = array('time_start'=>date('m-d H:i:s',$request->server['request_time']),'time_end'=>'');
        foreach($this->config->proxy as $proxyStr)
        {
            $tmp = $this->getProxyConfigObjFromStr($proxyStr);
            $str  = json_encode(array(
                'code'=>0,
                'data'=>$tmp->toString(),
            ));
            $clients->addTask($tmp->myIp, $tmp->myPort, '/'.MICRO_SERVICE_MODULENAME.'/proxy/updateConfig', $str);

        }
        $ret['result']=$clients->getResultsAndFree();
            
        $ret['time_end']= date('m-d H:i:s');
        $this->returnJsonResponse($response, $ret);
    }
    
    /**
     * proxy启动时 来要自己的配置文件
     */
    protected function getProxyConfig($request,$response)
    {
        $this->log->syslog(__FUNCTION__,array('proxyip'=>$request->server['remote_addr']));
        $proxyStr = $this->config->proxy[$request->server['remote_addr']];
        if(empty($proxyStr)){
            $response->status(404);
        }else{
            $tmp = $this->getProxyConfigObjFromStr($proxyStr);

            $this->returnJsonResponse($response, $tmp->toString());
        }
    }

}
