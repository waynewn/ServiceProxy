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
    public function chose($locations,$modulename,$timestamp)
    {
        $c = count($locations);
        
        for($n=0;$n<$c;$n++){
            $i = $this->getPointer($modulename);
            $tmp = $locations[ $i % $c];
            if(!$this->isErrorNode($tmp['ip'], $tmp['port'], $timestamp)){
                $location = new \Sys\Config\ServiceLocation();
                $location->ip = $tmp['ip'];
                $location->port=$tmp['port'];
                $this->addCounter($location->ip, $location->port, $timestamp);
                return $location;
            }
        }
        return Null;
    }
    /**
     * 记录轮询位置的数据，为方便调试，这里先给了public
     * @var array 
     */
    public $moduleIndex=array();
    /**
     * 获取轮询指针
     * @param string $modulename
     * @return int
     */
    private function getPointer($modulename)
    {
        if(!isset($this->moduleIndex[$modulename])){
            $this->moduleIndex[$modulename]=rand(1,99);
        }
        return $this->moduleIndex[$modulename]++;
    }
    

    

}
