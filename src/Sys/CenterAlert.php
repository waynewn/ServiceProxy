<?php
namespace Sys;
include __DIR__.'/Microservice.php';
/**
 * 把报警抽出来了
 *
 * @author wangning
 */
class CenterAlert  extends Microservice{
    /**
     *
     * @var \Sys\Config\CenterConfig
     */
    public $config;    
    /**
     *
     * @var \Sys\Log\Txt
     */
    protected $log;    
    public function onSwooleTask($serv, $task_id, $src_worker_id, $data)
    {
        //todo : 报警
        if($data['task']=='rptErrNode'){//'uri'=>$uri,'ip'=>$ip,'port'=>$port,'time'=>$request_time,'proxyIp'=>'')
            $this->onErr_ErrorNodeFound($data['uri'], $data['proxyIp'], $data['ip'], $data['port'],$data['time']);
        }
    }
    protected function onErr_ErrorNodeFound($uri,$fromProxy,$nodeIp,$nodePort,$request_time)
    {
        //默认在proxy已经处理掉了
    }
    public function onTimer($timer_id, $tickCounter)
    {
        $curl = \Sys\Util\Curl::factory();
        $allProxy = array();
        foreach ($this->config->proxy as $s){
            $tmp = $this->getProxyConfigObjFromStr($s);
            $allProxy[$tmp->myIp]=$tmp->myPort;
        }
        
        foreach($allProxy as $proxyIp=>$proxyPort){
            $ret = $curl->httpGet("http://$proxyIp:$proxyPort/".MICRO_SERVICE_MODULENAME.'/proxy/gatherByCenter');
            $arr = json_decode($ret,true);
            if(is_array($arr)){
                foreach($arr['proxy_sum'] as $ipport=>$num){
                    $this->log->syslog('proxyCounter '.$proxyIp.' => '.$ipport.' '.$num);
                }
            }else{
                $this->log->syslog('proxyCounterMiss '.$proxyIp.' http-code:'.$curl->httpCodeLast);
            }
        }
    }
}
