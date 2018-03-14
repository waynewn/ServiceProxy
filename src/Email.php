<?php
include 'config/MailService.php';
include 'Sys/Microservice.php';
include 'Sys/Util/Email/SmtpSSL.php';
dl("swoole.so");
/**
 * 微服务: 就一个发邮件的功能，配置在config/MailService.php中定义
 * 
 *     - 不依赖其他（不需要proxy代理请求）
 *     - 没缓存队列，当场接收当场发送，返回 发送成功，发送失败，已受理，接收任务失败（task池满了）
 *     - 不记录日志
 *     - 腾讯企业邮箱，测试通过，其他邮箱没试过
 *     - 基于swoole实现，没验证过超大参数或超大返回情况
 * 
 * 目前就实现了一个发送 (/MailService/Broker在配置中定义，可改) 
 *      /MailService/Broker/sendSync（同步）
 *      /MailService/Broker/sendSync（异步） 
 *      参数
 *           users: 逗号分割的接收人地址
 *           title：标题
 *           content: html格式的内容
 * 
 * 启动命令（先将 config/sample/MailService.php 复制到 config/MailService.php并相应修改）:
 *      php Email.php &
 *      php Email.php 指定端口 &
 */

class MailService extends \Sys\Microservice{
    
    public function dispatch($request,$response)
    {
        $users = $this->getReq($request, 'users');
        $title = $this->getReq($request, 'title');
        $content=$this->getReq($request, 'content');
        switch ($request->server['request_uri']){
            //proxy 来要自己的配置文件
            case MAILSERVICE_MODULE_CTRL.'/sendSync':
                if($this->send($users,$title,$content)){
                    $this->returnJsonResponse($response, array('code'=>0,'msg'=>'sent'));
                }else{
                    $this->returnJsonResponse($response, array('code'=>500,'msg'=>'send failed'));
                }
                break;
            case MAILSERVICE_MODULE_CTRL.'/sendAsync':
                $data = array('task'=>'mailAsync','users'=>$users,'title'=>$title,'content'=>$content);
                try{
                    if($this->swoole->task($data)===false){
                        $this->returnJsonResponse($response, array('code'=>500,'msg'=>'task full'));
                    }else{
                        $this->returnJsonResponse($response, array('code'=>0,'msg'=>'request accepted'));
                    }
                } catch (\ErrorException $ex) {
                    $this->returnJsonResponse($response, array('code'=>500,'msg'=>'task full'));
                }
                break;
            case MAILSERVICE_MODULE_CTRL.'/ping':
				$this->returnJsonResponse($response, array('code'=>0,'status'=>'ok'));
				break;
            default:
                $this->returnJsonResponse($response, array('code'=>404,'ret'=>'unknown cmd:'.$request->server['request_uri']));
        }
    }
    
    protected function send($users,$title,$content)
    {
        try{
            \Sys\Util\Email\SmtpSSL::factory('user='.MAILSERVICE_SENDER_USER.'&pass='.MAILSERVICE_SENDER_PASSWORD.'&server='.MAILSERVICE_MAILSERVER_ADDR)
                    ->sendTo($users, $content, $title);
            return true;
        }catch(\ErrorException $ex){
            return false;
        }
    }
    
    public function onSwooleTask($serv, $task_id, $src_worker_id, $data)
    {
        return $this->send($data['users'], $data['title'], $data['content']);
    }
}

if(!empty($argv[1])){
    $portListen = $argv[1]-0;
}else{
    $portListen = MAILSERVICE_PORT;
}

$srv = new MailService();
$http = new swoole_http_server('0.0.0.0',$portListen);
$http->set(array(
    'worker_num' =>MAILSERVICE_MAX_REQUEST,
    'task_worker_num'=>MAILSERVICE_MAX_TASK,//因为主要是处理报警任务，所以100个足够了
//    'daemonize' => true,
//    'backlog' => 128,
));
$srv->initSwooleServer($http);
$http->on("request", function ($request, $response) use ($srv) {
    $srv->dispatch($request, $response);
});
$http->on("task", function ($serv, $task_id, $src_worker_id, $data) use ($srv){
    $srv->onSwooleTask($serv, $task_id, $src_worker_id, $data);
}); 
$http->on("finish", function ($serv,$task_id, $data) use ($srv){
    //error_log("task-------------------------------$data finished");
}); 
$http->start();
