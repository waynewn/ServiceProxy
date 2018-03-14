<?php
include 'config/ServiceProxy.php';
include 'Sys/Util/Funcs.php';
include 'Sys/Config/ProxyConfig.php';
include 'Sys/Config/CenterConfig.php';
include 'Sys/Config/MonitorConfig.php';
include 'Sys/Config/LogConfig.php';
include 'Sys/Config/XML2CenterConfig.php';
include 'Sys/Center.php';
include 'Sys/Log/Txt.php';
dl("swoole.so");

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
$http = new swoole_http_server('0.0.0.0',$center->getMyPort());
$http->set(array(
    'worker_num' =>$worker_num,
    'task_worker_num'=>MAX_TASK_NUM,//因为主要是处理报警任务，所以100个足够了
	'log_file'=>$center->config->LogConfig->root.'/CENTER_swoole.log',
    'daemonize' => true,
//    'backlog' => 128,
));
$center->initSwooleServer($http);
$http->on("request", function ($request, $response) use ($center) {
    $center->dispatch($request, $response);
});
$http->on("task", function ($serv, $task_id, $src_worker_id, $data) use ($center){
    $center->onSwooleTask($serv, $task_id, $src_worker_id, $data);
}); 
$http->on("finish", function ($serv,$task_id, $data) use ($center){
    //error_log("task-------------------------------$data finished");
}); 
$center->onListenStart();
$http->start();