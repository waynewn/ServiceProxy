<?php
namespace Sys;
include __DIR__.'/Microservice.php';
include __DIR__.'/Util/Curl.php';
include __DIR__.'/Util/Email/SmtpSSL.php';
/**
 * 把报警抽出来了
 *
 * @author wangning
 */
class ProxyAlert  extends Microservice{
    /**
     *
     * @var \Sys\Config\ProxyConfig
     */
    public $config;
    /**
     * proxy fork 的task异步进程在处理 错误节点问题
     * @param type $uri
     * @param type $fromProxy
     * @param type $nodeIp
     * @param type $nodePort
     * @param type $request_time
     */
    protected function onErr_ErrorNodeFound($uri,$fromProxy,$nodeIp,$nodePort,$request_time)
    {
        // 通过mail service 发邮件
        $curl = \Sys\Util\Curl::factory();
        $args = array(
            'users'=> explode(',', $this->config->monitorConfig->usersgroup['ErrorNode']),
            'title'=>'发现宕机节点',
            'content'=>(is_numeric($request_time)?date('m-d H:i:s',$request_time):$request_time)." ".$fromProxy.' -> '.$nodeIp.':'.$nodePort.' '.$uri,
        );
        $hostForMail = $this->config->getRouteFor($this->config->monitorConfig->services['email'],$request_time);
        $curl->httpGet('http://'.$hostForMail->ip.':'.$hostForMail->port.$this->config->monitorConfig->services['email'].'?'.http_build_query($args), array(), 1);

        //向中央上报，后续由CenterAlert类中定义的task进程接手处理
        $data = array(
                        'task'=>'rptErrNode',
                        'uri'=>$uri,
                        'ip'=>$nodeIp,
                        'port'=>$nodePort,
                        'time'=>$request_time,
                        'proxyIp'=>$this->config->myIp,
                        );
        $curl->httpGet('http://'.$this->config->centerIp.':'.$this->config->centerPort.'/'.MICRO_SERVICE_MODULENAME.'/center/noticeFromProxy?'. http_build_query($data));
        
    }
}
