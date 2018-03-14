<?php
namespace Sys;
use \Sys\Util\Funcs as Funcs;
include __DIR__.'/CenterBase.php';

/**
 * 中央控制
 * todo ip限制
 * @author wangning
 */
class CenterReport extends CenterBase{

    public function dispatchReports($request,$response)
    {
        switch ($request->server['request_uri']){
//            case '/'.MICRO_SERVICE_MODULENAME.'/center/proxylist':
//                $this->log->managelog('proxylist');
//                $this->searchProxyList($request, $response);
//                return true;

            // 获取proxy状态, 可以通过ips可选参数（逗号分割的ip列表）获取指定proxy的状态
            case '/'.MICRO_SERVICE_MODULENAME.'/center/proxisStatus':
                $this->proxiesStatus($request, $response);
                return true;
            // 获取最近一段时间，各个proxy总代理请求数
            case '/'.MICRO_SERVICE_MODULENAME.'/center/proxisCounter':
                $this->proxiesStatus($request, $response);
                return true;
                
            // 获取路由目标节点统计,可以通过namelike可选参数, 获取名称包含指定字符串的结果
            case '/'.MICRO_SERVICE_MODULENAME.'/center/routeSummary':
                $this->routeSummary($request, $response);
                return true;
                
            default:
                return false;
        }
    }
    protected function proxisCounter($request,$response)
    {
        $timeNow = $request->server['request_time'];
        $minuteThis = date('m-d H:i',$timeNow);
        $minutePre= date('m-d H:i',$timeNow-60);
        //$this->log->trace("minuteThis=>$minuteThis,minutePre=>$minutePre");        
        try{
            $r = array_keys($this->config->proxy);
            $ret = $this->getProxiesStatus($r, 1);
            $finalRet=array();

            foreach($ret as $k=>$r){
                $pos = strpos($k, '/',8);
                $k2 = substr($k, 7,$pos-7);
                $finalRet[$k2]=$r;
                
                foreach($finalRet[$k2]['proxy']['counter'] as $timestamp=>$r)
                {
                    if($timestamp==$minutePre || $timestamp==$minuteThis){
                        $finalRet[$timestamp][$k2] = array_sum($r);
                    }
                }
            }
            
            $this->returnJsonResponse($response, array('code'=>200,'proxiesCounter'=>$finalRet));
        }catch(\ErrorException $ex){
            $this->returnJsonResponse($response, array('code'=>400,'msg'=>$ex->getMessage()));
        }
    }
    protected function proxiesStatus($request,$response)
    {
        $timeNow = $request->server['request_time'];
        $minuteThis = date('m-d H:i',$timeNow);
        $minutePre= date('m-d H:i',$timeNow-60);
        $ips = $this->getReq($request, 'ips');
        try{
            if(empty($ips)){
                $r = array_keys($this->config->proxy);
            }else{
                $r = explode(',', $ips);
                foreach($r as $k=>$v){
                    $r[$k]=Funcs::getIp($v);
                }
            }
            $ret = $this->getProxiesStatus($r, 1);
            $finalRet=array();
            //$this->log->trace("minuteThis=>$minuteThis,minutePre=>$minutePre");
            foreach($ret as $k=>$r){
                $pos = strpos($k, '/',8);
                $k2 = substr($k, 7,$pos-7);
                $finalRet[$k2]=$r;
                
                foreach($finalRet[$k2]['proxy']['counter'] as $timestamp=>$r)
                {
                    if($timestamp==$minutePre || $timestamp==$minuteThis){
                        $finalRet[$k2]['proxy']['counter'][$timestamp] = array_sum($r);
                    }else{
                        unset($finalRet[$k2]['proxy']['counter'][$timestamp]);
                    }
                }
                unset($finalRet[$k2]['proxyip']);
                unset($finalRet[$k2]['request_time']);
            }
            
            $this->returnJsonResponse($response, array('code'=>200,'proxiesStatus'=>$finalRet));
        }catch(\ErrorException $ex){
            $this->returnJsonResponse($response, array('code'=>400,'msg'=>$ex->getMessage()));
        }
    }
    protected function routeSummary($request,$response)
    {
        $namelike = $this->getReq($request, 'namelike');
        $map = array();
        if(empty($namelike)){
            foreach($this->config->nodeLocation as $name=>$ipPort){
                $map[$ipPort['ip'].':'.$ipPort['port']] = $name;
            }
        }else{
            foreach($this->config->nodeLocation as $name=>$ipPort){
                if(strpos($name, $namelike)!==false){
                    $map[$ipPort['ip'].':'.$ipPort['port']] = $name;
                }
            }
        }
        $timeNow = $request->server['request_time'];
        $minuteThis = date('m-d H:i',$timeNow);
        $minutePre= date('m-d H:i',$timeNow-60);        
        try{
            $ret = $this->getProxiesStatus(array_keys($this->config->proxy), 1);
            $summary = array(); //格式 $summary[$timestamp][$ip]=$num;  
            foreach ($ret as $r){
                foreach($r['proxy']['counter'] as $timestamp=>$ip_num){
                    if($timestamp!==$minutePre && $timestamp!==$minuteThis){
                        continue;
                    }
                    foreach ($ip_num as $ip=>$num){
                        if(isset($map[$ip])){
                            $summary[$timestamp][ $map[$ip] ]+=$num;
                        }
                    }
                }
            }
            ksort($summary);
            //todo： 翻译检查namelike
            //todo： error-report
            $this->returnJsonResponse($response, array('code'=>200,'routeSummary'=>$summary));
        }catch(\ErrorException $ex){
            $this->returnJsonResponse($response, array('code'=>400,'msg'=>$ex->getMessage()));
        }
    }
    /**
     * 获取当前代理列表
     * 
     * 可选参数：
     *  proxyip:   指定ip的proxy
     *  nodename:  包含指定节点名的那些proxy
     *  service:   包含指定服务的那些proxy
     * 返回
     * 	{
     * 		ip_port（proxy1）:{
     * 			name_port（node1）=>status,
     * 			name_port（node2）=>status,
     * 		}
     * 	}
     */
    protected function searchProxyList($request, $response)
    {
        $this->log->managelog(__FUNCTION__);
        $limitIp = $this->getReq($request, 'proxyip');
        $limitNodeNameLike = $this->getReq($request, 'nodename');
        $limitServiceNameLike = $this->getReq($request, 'service');
        $nodesRealLimit=array();//搜索时的条件是包含，实际搜索后，这里记录实际的nodename
        $ips = array();//这里记录实际涉及到的proxyip
        if(!empty($limitNodeNameLike)){
            foreach($this->config->nodeLocation as $nodeName=>$r){
                if(false!== strpos($nodeName, $limitNodeNameLike)){
                    $ips[] = $r['ip'];
                    $nodesRealLimit[$nodeName]=$nodeName;
                }
            }
        }elseif(!empty($limitServiceNameLike)){
            $tmp = $this->config->getServiceMap();
            foreach($tmp as $serviceName=>$rs){
                if(false!== strpos($serviceName, $limitServiceNameLike)){
                    foreach($rs as $r){
                        $ips[]=$r['ip'];
                    }
                    $nodesRealLimit = array_merge($nodesRealLimit,$this->config->serviceInNode[$serviceName]);
                }
            }
        }
        //在node、service限制基础上增加ip限制
        if(!empty($limitIp)){
            if(!empty($ips)){
                if(in_array($limitIp, $ips)){
                    $ips=array($limitIp);
                }else{
                    $ips=array();
                }
            }else{
                $ips=array($limitIp);
            }
        }else{
            if(empty($ips)){
                $ips= array_keys($this->config->proxy);
            }
        }

        if(empty($ips)){
            $this->returnJsonResponse($response, array());
        }else{
            $this->returnJsonResponse($response, $this->getProxiesStatus($ips,$nodesRealLimit));
        }
    }
}
