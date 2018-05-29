<?php
namespace Sys;
/**
 * 微服务中间件基类
 *
 * @author wangning
 */
class Microservice {
    public function __construct() {
        $this->taskRunning = new \Swoole\Atomic\Long();
    }
    /**
     * 当前正在运行的任务书数
     * @var \Swoole\Atomic\Long
     */
    protected $taskRunning=0;
    public function taskRunning_inc()
    {
        $this->taskRunning->add(1);
    }
    public function taskRunning_dec()
    {
        $this->taskRunning->sub(1);
    }
    public function getTaskRunningCounter()
    {
        return $this->taskRunning;
    }
    
    public function dispatch($request,$response){
        $this->taskRunning_inc();
        if ($request->server['request_uri']=='/isThisNodeHealth'){
            $this->nodeHealthCheck($response);
        }elseif($request->server['request_uri']=='/shutdownThisNode'){
            $this->nodeShutdown($response);
        }
    }
    
    
    /**
     * 当前服务器节点健康检查
     * @param type $response
     */
    protected function nodeHealthCheck($response)
    {
        $this->returnJsonResponse($response,array('code'=>0,
                    'task_running'=> $this->taskRunning->get()));
    }
    protected function nodeShutdown($response)
    {
        $this->returnJsonResponse($response,array('code'=>0,'msg'=>'shutdown command sent'));
        $this->swoole->shutdown();
    }
    
    /**
     * 输出结果（json格式）
     * @param type $response
     * @param type $ret
     */
    protected function returnJsonResponse($response,$ret)
    {
        $this->taskRunning_dec();
        $response->header("Content-Type", "application/json");
        if(!is_string($ret)){
            $response->end(json_encode($ret));
        }else{
            $response->end($ret);
        }
    }
    
    protected function getReq($request,$key)
    {
        if(isset($request->get[$key])){
            return $request->get[$key];
        }elseif(isset($request->post[$key])){
            return $request->post[$key];
        }else{
            return null;
        }
    }
    
    /**
     * 输出结果（txt格式）
     * @param type $response
     * @param type $ret
     */
    protected function returnTxtResponse($response,$ret)
    {
        $this->taskRunning_dec();
        $response->end($ret);
    }

    protected $swoole;
    public function initSwooleServer($swoole)
    {
        $this->swoole = $swoole;
    }
    public function onSwooleTask($serv, $task_id, $src_worker_id, $data){}
    public function onSwooleTaskReturn($serv, $task_id, $data){}
    /**
     * 执行task的时候，捕获到异常
     * @param \ErrorException $ex
     * @param array  $data task-data
     * @param bool $throwAtFinish false：执行任务时，true：任务执行完finish阶段
     * @return mixed 当作task返回的值
     */
    public function onTaskError($ex,$data,$throwAtFinish=false)
    {
    }
}
