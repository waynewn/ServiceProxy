<?php
namespace Sys;
use \Sys\Util as util;
/**
 * 微服务中间件基类
 *
 * @author wangning
 */
class Microservice {
    /**
     * 输出结果（json格式）
     * @param type $response
     * @param type $ret
     */
    protected function returnJsonResponse($response,$ret)
    {
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
        $response->end($ret);
    }

    protected $swoole;
    public function initSwooleServer($swoole)
    {
        $this->swoole = $swoole;
    }
    public function onSwooleTask($serv, $task_id, $src_worker_id, $data){}

}
