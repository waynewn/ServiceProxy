<?php
include 'Sys/Util/Funcs.php';
include 'Sys/Proxy.php';

if(!class_exists('swoole_http_server',false)){
    dl("swoole.so");
}

/**
 * 问中心服务器要自己的配置
 * @param type $ip
 * @param type $port
 */

$cmd = \Sys\Util\Funcs::parseCmdArg($argv);
$worker_num=isset($cmd['-worker'])?($cmd['-worker']-0):0;
if($worker_num==0){
    $worker_num=5;
}
if(isset($cmd['-center']) ){
    $proxy = new \Sys\Proxy();
    $proxy->initByCenterURL($cmd['-center']);
    if( $proxy->isConfigLoadedSuccessfully==false){
        die("get config failed,check ip and port\n ");
    }
}else{
    die("unknown cmds, use -h for help\n ");
}

$http = new swoole_http_server($proxy->getMyIp(), $proxy->getMyPort());
$http->set(array(
    'worker_num' =>$worker_num,
    'task_worker_num'=>MAX_TASK_NUM,//因为主要是处理报警任务，所以100个足够了
	'log_file'=>$proxy->config->LogConfig->root.'/'.$proxy->getMyIp().'_swoole.log',
    'daemonize' => true,
//    'backlog' => 128,
));
if($proxy->getMyIp()!='127.0.0.1'){
    $http->listen('127.0.0.1',$proxy->getMyPort(),SWOOLE_SOCK_TCP);
}
$proxy->initSwooleServer($http);
$http->on("request", function ($request, $response) use ($proxy) {
    $proxy->dispatch($request, $response);
});
$http->on("task", function ($serv, $task_id, $src_worker_id, $data) use ($proxy){
    $proxy->taskRunning_inc();
    try{
        $ret = $proxy->onSwooleTask($serv, $task_id, $src_worker_id, $data);
        if(empty($ret)){
            $proxy->taskRunning_dec();
        }else{
            return $ret;
        }
    }catch(\ErrorException $e){
        $ret = $proxy->onTaskError($e,$data,false);
        if(empty($ret)){
            $proxy->taskRunning_dec();
        }else{
            return $ret;
        }
    }
}); 
$http->on("finish", function ($serv,$task_id, $data) use ($proxy){
    try{
        $proxy->onSwooleTaskReturn($serv, $task_id, $data);
    }catch(\ErrorException $ex){
        $proxy->onTaskError($ex,$data,true);
    }
    $proxy->taskRunning_dec();
});
$proxy->onListenStart();
$http->start();