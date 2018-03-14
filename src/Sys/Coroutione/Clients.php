<?php
namespace Sys\Coroutione;
include __DIR__.'/Client.php';
/**
 * Description of Clients
 *
 * @author wangning
 */
class Clients {
    /**
     * @return \Sys\Coroutione\Clients
     */
    public static function create($timeout=5)
    {
        $o =  new Clients();
        $o->timeout=$timeout;
        return $o;
    }
    protected $timeout=5;
    /**
     *
     * @var \Sys\Log\Txt
     */
    protected $log;
    protected $arr=array();
    public function addTask($ip,$port,$uriWithQueryString,$args4Post=null)
    {
        $this->arr["http://$ip:$port/$uriWithQueryString"] = new \Sys\Coroutione\Client($ip,$port,$uriWithQueryString,$args4Post,$this->timeout);
    }

    /**
     * 获取所有请求的返回值，
     *      httpcode 200的是字符串，其他的是数字（http code）
     * 
     * @param bool $isLastTry 是否跳过超时设置直接拿结果（当超时处理），默认否
     * @return array
     */
    public function getResultsAndFree($isLastTry=false)
    {
        $secondsSleepPerStep=0.2;
        $stepMax = ceil($this->timeout/$secondsSleepPerStep);
        $ret = array();
        if($isLastTry==false){
            for($i=1;$i<$stepMax;$i++){//少一次用于最后强制结束的循环
                foreach($this->arr as $k=>$client){
                    $tmp = $client->tryGetResultAndFree();
                    if($tmp!==null){
                        unset($this->arr[$k]);
                        $ret[$k] = $tmp;
                    }
                }
                if(sizeof($this->arr)){
                    \co::sleep($secondsSleepPerStep);
                }
            }
        }
        foreach($this->arr as $k=>$client){
            $tmp = $client->tryGetResultAndFree(true);
            unset($this->arr[$k]);
            $ret[$k] = $tmp;
        }
        return $ret;
    }
}
