<?php
namespace Sys\LoadBalance;

/**
 * 负载均衡基类：记录、确认问题节点
 *
 * @author wangning
 */
class Base {
    protected $error=null;
    public function __construct() {
        $maxTotalNode = MAX_SERVICES * MAX_NODE_PER_SERVICE;
        $this->newCounter = new \swoole_table($maxTotalNode);
        $this->newCounter->column('sum', \swoole_table::TYPE_INT, 8); 
        $this->newCounter->column('cur', \swoole_table::TYPE_INT, 8); 
        $this->newCounter->create();
        
        $this->error = new \swoole_table(100);
        $this->error->column('timestamp', \swoole_table::TYPE_INT, 8);       //1,2,4,8
        $this->error->column('num', \swoole_table::TYPE_INT, 4);
        $this->error->create();
        
    }
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

                if($timestamp-$this->error[$flg]['timestamp']>EXPIRE_SECONDS_NODE_FAILED){
                    $this->error[$flg]['num']=1;
                }else{
                    $this->error[$flg]['num']++;
                }
                $this->error[$flg]['timestamp']=$timestamp;
            }
            if(count($this->error)>10){
                foreach($this->error as $flg=>$r){
                    if($timestamp-$r['timestamp']>EXPIRE_SECONDS_NODE_FAILED){
                        //unset($this->error[$flg]);
                        $this->error->del($flg);
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
            }elseif($timestamp-$this->error[$flg]['timestamp']>EXPIRE_SECONDS_NODE_FAILED){
                unset($this->error[$flg]);
                return false;
            }else{
                return true;
            }
        }else{
            return false;
        }
    }

    protected $newCounter=null;
    public function onProxyStart($location)
    {
        $flg = $location->ip.':'.$location->port;
        if($this->newCounter->exist($flg)){
            $new = $this->newCounter->incr($flg,'sum',1);
            if($new>8000111110000011111){
                $this->newCounter->decr($flg,'sum',20111110000011111);
            }
            $this->newCounter->incr($flg,'cur',1);
        }else{
            $this->newCounter->set($flg,array('sum'=>1,'cur=>1'));
        }
    }
    /**
     * 
     * @param type $location
     */
    public function onProxyEnd($location)
    {
        $flg = $location->ip.':'.$location->port;
        $this->newCounter->decr($flg,'cur',1);
    }
    
    public function proxyCounterReset()
    {
        $ret = array();
        foreach($this->newCounter as $k=>$r){
            if($r['sum']>0){
                $ret[$k]=$r['sum'];
                $this->newCounter->decr($k,'sum',$r['sum']);
            }
        }
        return $ret;
    }
    /**
     * 获取状态
     * array(
    'counter' =>  array (
      '05-28 13:17' =>  array (
        '10.30.232.9:8881' => 1,
        '10.30.232.9:8882' => 1,
      ),

    ),
    'error' =>  array (
      '10.30.232.9:8881' => array (
        'timestamp' => 1527482691,
        'num' => 1,
      ),
      '10.30.232.9:8882' => array (
        'timestamp' => 1527482650,
        'num' => 1,
      ),
    ),

     * )
     * @return array 
     */
    public function status()
    {
        $counter = array();
        foreach($this->newCounter as $ipPort=>$r){
            if($r['cur']>0){
                $counter[$ipPort]=$r['cur'];
            }
        }
        return array(
            'counter'=> array(date('m-d H:i')=>$counter),
            'error'=> $this->dumpSwooleTable($this->error),
            //'debug'=>$this->dumpCounter(),
        );
    }
    protected function dumpSwooleTable($o)
    {
        $ret = array();
        foreach($o as $k=>$r){
            $ret[$k]=$r;
        }
        return $ret;
    }
}
