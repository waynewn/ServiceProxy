<?php
namespace Sys\LoadBalance;

/**
 * 负载均衡基类：记录、确认问题节点
 *
 * @author wangning
 */
class Base {
    protected $error=array();
    /**
     * 记录问题节点
     * @param type $ip
     * @param type $port
     * @param type $timestamp
     */
    public function markNodeDown($ip,$port,$timestamp)
    {
        if(EXPIRE_SECONDS_NODE_FAILED){
            $flg = $ip.':'.$port;
            if(!isset($this->error[$flg])){
                $this->error[$flg]['timestamp']=$timestamp;
                $this->error[$flg]['num']=1;
            }else{

                if($this->error[$flg]['timestamp']-$timestamp>EXPIRE_SECONDS_NODE_FAILED){
                    $this->error[$flg]['num']=1;
                }else{
                    $this->error[$flg]['num']++;
                }
                $this->error[$flg]['timestamp']=$timestamp;
            }
            if(sizeof($this->error)>10){
                foreach($this->error as $flg=>$r){
                    if($this->error[$flg]['timestamp']-$timestamp>EXPIRE_SECONDS_NODE_FAILED){
                        unset($this->error[$flg]);
                    }
                }
            }
        }
    }
    /**
     * 判定节点是否有问题（失败一次可以当没问题的，多次的话上次问题时间距离现在是否超过5分钟了）
     * @param type $ip
     * @param type $port
     * @param type $timestamp
     * @return bool
     */
    protected function isErrorNode($ip,$port,$timestamp)
    {
        if(EXPIRE_SECONDS_NODE_FAILED){
            $flg = $ip.':'.$port;
            if(!isset($this->error[$flg])){
                return false;
            }elseif($this->error[$flg]['num']==1){
                return false;
            }elseif($this->error[$flg]['timestamp']-$timestamp>EXPIRE_SECONDS_NODE_FAILED){
                unset($this->error[$flg]);
                return false;
            }else{
                return true;
            }
        }else{
            return false;
        }
    }
    /**
     * 获取状态
     * array(
     * 
     * )
     * @return array 
     */
    public function status()
    {
        return array(
            'counter'=> $this->counter,
            'error'=> $this->error
        );
    }
    /**
     * 请求计数，保留最近5个记录（不是最近5分钟）
     * array(
     *      '时间（精确到分钟）'=>array(
     *          'ip:port'=>123,
     *      ),
     *      '时间（精确到分钟）'=>array(
     *          'ip:port'=>123,
     *      )
     * )
     * @var array 
     */
    public $counter=array();
    /**
     * 记录一次分配
     */
    protected function addCounter($ip,$port,$timestamp)
    {
        $this->counter[date('m-d H:i',$timestamp)][$ip.':'.$port]++;
        if(sizeof($this->counter)>5){
            array_shift($this->counter);
        }
    }
}
