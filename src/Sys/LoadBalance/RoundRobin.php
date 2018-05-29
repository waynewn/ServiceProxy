<?php
namespace Sys\LoadBalance;
include __DIR__.'/Base.php';
/**
 * 轮询
 *
 * @author wangning
 */
class RoundRobin extends Base{
    /**
     * 按规则选择一个地址
     * @param array $locations array(  [ip=>1.2.3.4, port=>1.2.3.4] )
     * @return \Sys\Config\ServiceLocation
     */
    public function chose($locations,$modulename,$timestamp,$ignoreIpPort=null)
    {
        $c = count($locations);
        $i0 = $this->getPointer($modulename);
        $i=$i0;
        for($n=0;$n<$c;$n++,$i++){
            $tmp = $locations[ $i % $c];
            if($tmp['ip'].':'.$tmp['port']==$ignoreIpPort){
                continue;
            }
            if(!$this->isErrorNode($tmp['ip'], $tmp['port'], $timestamp)){
                $location = new \Sys\Config\ServiceLocation();
                $location->ip = $tmp['ip'];
                $location->port=$tmp['port'];
//                $this->addCounter($location->ip, $location->port, $timestamp);
                if($i!=$i0){
                    $this->moduleIndex->set($modulename,array('index'=>$i));
                }
                return $location;
            }
        }
        return Null;
    }
    public function workAsGlobal() {
        $this->moduleIndex = new \swoole_table(MAX_SERVICES);
        $this->moduleIndex->column('index', \swoole_table::TYPE_INT, 4);
        $this->moduleIndex->create();
    }
    
    /**
     * 记录轮询位置的数据，为方便调试，这里先给了public
     * @var array 
     */
    protected $moduleIndex=null;
    /**
     * 获取轮询指针
     * @param string $modulename
     * @return int
     */
    private function getPointer($modulename)
    {
        $index = $this->moduleIndex->incr($modulename,'index');
        if($index>=10000000){
            if($index%1000==0){
                $this->moduleIndex->set($modulename,array('index'=>1));
            }
        }
        return $index;
    }
    

    

}
