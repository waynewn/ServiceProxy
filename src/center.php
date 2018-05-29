<?php
include 'Sys/Util/Funcs.php';
include 'Sys/Config/XML2CenterConfig.php';
include 'Sys/Center.php';
include 'Sys/Log/Txt.php';
if(!class_exists('swoole_http_server',false)){
    dl("swoole.so");
}

$cmd = \Sys\Util\Funcs::parseCmdArg($argv);
$worker_num=isset($cmd['-worker'])?($cmd['-worker']-0):0;
if($worker_num==0){
    $worker_num=5;
}
if(isset($cmd['-t'])){
    try{
        $config = \Sys\Config\XML2CenterConfig::parse($cmd['-t']);
    }catch(\ErrorException $e){
        die ("\nerror: ".$e->getMessage()."\n");
    }
    if(empty($config->configVersion)){
        die("config-xml has error\n");
    }else{
        echo "ok\n";
        exit;
    }
}elseif(isset($cmd['-c'])){
    if(substr($cmd['-c'],0,1)!=='/'){
        $workdir = exec('cd $(dirname $0); pwd');
        $cmd['-c']= $workdir.'/'.$cmd['-c'];
    }
    $center = new \Sys\Center($cmd['-c']);
    if($center->reloadConfig()==false){
        die("parse config-xml failed\n");
    }
}else{
    die("unknown cmds, use -h for help\n ");
}
$http = new swoole_http_server($center->getMyIp(),$center->getMyPort());
$http->set(array(
    'worker_num' =>$worker_num,
    'task_worker_num'=>MAX_TASK_NUM,//因为主要是处理报警任务，所以100个足够了
	'log_file'=>$center->config->LogConfig->root.'/CENTER_swoole.log',
    'daemonize' => true,
//    'backlog' => 128,
));
if($center->getMyIp()!='127.0.0.1'){
    $http->listen('127.0.0.1',$center->getMyPort(),SWOOLE_SOCK_TCP);
}
$center->initSwooleServer($http);
$http->on("start", array($center,'onListenStart'));
$http->on("request", array ($center,'dispatch'));
$http->on("task", function ($serv, $task_id, $src_worker_id, $data) use ($center){
    $center->taskRunning_inc();
    try{
        $ret = $center->onSwooleTask($serv, $task_id, $src_worker_id, $data);
        if(empty($ret)){
            $center->taskRunning_dec();
        }else{
            return $ret;
        }
    }catch(\ErrorException $e){
        $ret = $center->onTaskError($e,$data,false);
        if(empty($ret)){
            $center->taskRunning_dec();
        }else{
            return $ret;
        }
    }    
}); 
$http->on("finish", function ($serv,$task_id, $data) use ($center){ 
    try{
        $center->onSwooleTaskReturn($serv, $task_id, $data);
    }catch(\ErrorException $ex){
        $center->onTaskError($ex,$data,true);
    }
    $center->taskRunning_dec();
}); 
$http->start();