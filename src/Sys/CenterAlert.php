<?php
namespace Sys;
include __DIR__.'/Microservice.php';
/**
 * 把报警抽出来了
 *
 * @author wangning
 */
class CenterAlert  extends Microservice{
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
}
