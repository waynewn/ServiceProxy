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
        $this->error = new \swoole_table(100);
        $this->error->column('timestamp', \swoole_table::TYPE_INT, 8);       //1,2,4,8
        $this->error->column('num', \swoole_table::TYPE_INT, 4);
        $this->error->create();
        
        $this->counter = new \swoole_table(1);
        $this->counter->column('dt', \swoole_table::TYPE_INT, 4);
        $this->counter->column('index', \swoole_table::TYPE_INT, 4);
        $this->counter->create();
        $this->counter->set('0',array('dt'=>0,'index'=>0));
        
        $this->counter5Time = new \swoole_table($this->counterSize);
        $this->counter5Time->column('dt', \swoole_table::TYPE_STRING, 12);
        $this->counter5Time->create();
        
        $maxTotalNode = MAX_SERVICES * MAX_NODE_PER_SERVICE;
        
        for($i=0;$i<$this->counterSize;$i++){
            $this->counter5Num[$i] = new \swoole_table($maxTotalNode);
            $this->counter5Num[$i]->column('num', \swoole_table::TYPE_INT, 4);
            $this->counter5Num[$i]->create();
        }

    }
    
    protected $counterSize = 5;


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
    /**
     * 获取状态
     * array(
     * 
     * )
     * @return array 
     */
    public function status()
    {
        $i  = $this->getCounterIndex(time())+$this->counterSize;
        
        return array(
            'counter'=> array_merge($this->getCounterIn($i),$this->getCounterIn($i-1),$this->getCounterIn($i-2)),
            'error'=> $this->dumpSwooleTable($this->error),
            //'debug'=>$this->dumpCounter(),
        );
    }
    protected function getCounterIn($index)
    {
        $index = $index% $this->counterSize;
        $time = $this->counter5Time->get($index,'dt');
        if(empty($time)){
            return array();
        }else{
            $ret = array();
            foreach($this->counter5Num[$index] as $ipPort=>$r){
                $ret[$ipPort]=$r['num'];
            }
            return array($time=>$ret);
        }
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
    
    protected $counter=null;
    /**
     * index 对应的时间(5个)
     * @var swoole_table
     */
    protected $counter5Time=null;
    /**
     * index对应的5个swoole_table
     * 每个table 记录 node对应的代理num
     * @var array 
     */
    protected $counter5Num = array();
    /**
     * 记录一次分配
     */
    protected function addCounter($ip,$port,$timestamp)
    {
        $flg = $ip.':'.$port;
        $counterIndex = $this->getCounterIndex($timestamp);
        if($this->counter5Num[$counterIndex]->exist($flg)){
            $this->counter5Num[$counterIndex]->incr($flg,'num');
        }else{
            $this->counter5Num[$counterIndex]->set($flg,array('num'=>1));
        }
    }
    
    protected function dumpSwooleTable($o)
    {
        $ret = array();
        foreach($o as $k=>$r){
            $ret[$k]=$r;
        }
        return $ret;
    }
    public function dumpCounter()
    {
        $ret = array();
        $ret['pointer']= $this->dumpSwooleTable($this->counter);
        $ret['time'] = $this->dumpSwooleTable($this->counter5Time);
        foreach($this->counter5Num as $k=>$o){
            $ret['data'][$k] = $this->dumpSwooleTable($o);
        }
        return $ret;
    }
    
    protected function getCounterIndex($timestamp)
    {
        $k = date('mdHi',$timestamp);
        $cur = $this->counter->get(0);
        if($cur['dt']!=$k){
            $newIndex = ($cur['index']+1)%$this->counterSize;
            $this->counter->set('0',array('dt'=>$k,'index'=>$newIndex));
            $this->counter5Time[$newIndex]['dt']=date('m-d H:i',$timestamp);
            $nextIndex = ($newIndex+1)%$this->counterSize;
            \Sys\Util\Funcs::emptySwooleTable($this->counter5Num[$nextIndex]);
            return $newIndex;
        }else{
            return $cur['index'];
        }
    }
}
