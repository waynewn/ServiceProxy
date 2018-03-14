<?php
namespace Sys\Config;
include __DIR__.'/../LoadBalance/RoundRobin.php';
/**
 * 代理节点的配置项
 *
 * @author wangning
 */
class ProxyConfig {
    /**
     * 
     * @param string $str
     * @return \Sys\Config\ProxyConfig
     */
    public static function factory($str=null) {
        return unserialize($str);
    }
    
    /**
     * 
     * @return string
     */
    public function toString(){
        return serialize($this);
    }
    /**
     *
     * @var \Sys\Config\LogConfig 
     */
    public $LogConfig;
    /**
     *
     * @var \Sys\Config\MonitorConfig  
     */
    public $monitorConfig;
    /**
     * 上级配置中心的地址
     * @var string 
     */
    public $centerIp = "127.0.0.1";
    public $centerPort=0;
    /**
     * 代理的地址端口
     * @var type 
     */
    public $myIp;
    public $myPort=0;
    protected $rewriteRule=array(
        //  'from'=>'to'
    );
    protected $rewriteRuleIndex=0;
    protected $serviceMap=array(
        /**
        'serviceModule名字'=>array(
            ['ip'=>'127.0.0.1','port'=>123],
        ),
         */
    );
    protected $serviceMapIndex=0;
    /**
     *
     * @var \Sys\LoadBalance\RoundRobin 
     */
    protected $loadBalance=null;
    /**
     * 记录轮询位置
     * @var type 
     */
    protected $serviceNext=array();
    /**
     * 本机上部署了哪些节点，启动停止等管理命令的地址是多少
     * @var type 
     */
    public $nodeList=array(
        /**
        'node名字'=>array(
            'start'=>'启动命令',
            'stop'=>'停止命令',
            'ping'=>'ping命令',
            'active'=>'需要进入什么状态，yes 还是 no',
        ),
         */
    );
    public $configVersion='0.0.0';
    /**
     * 
     * @param \Sys\Config\ProxyConfig $newObj
     */
    public function copyFrom($newObj)
    {
        if($this->loadBalance===null){
            $this->loadBalance = new \Sys\LoadBalance\RoundRobin();
        }
        $this->LogConfig = $newObj->LogConfig;
        $newObj->LogConfig=null;
        $this->monitorConfig = $newObj->monitorConfig;
        $newObj->monitorConfig=null;
        $this->myIp = $newObj->myIp;
        $this->myPort=$newObj->myPort;
        $this->setRewrite($newObj->getRewrite());
        $this->setServiceMap($newObj->getServiceMap());
        $this->configVersion=$newObj->configVersion;
        $this->nodeList = $newObj->nodeList;
    }

    public function setRewrite($rewrite)
    {
        $newIndex = ($this->rewriteRuleIndex+1)%2;
        $this->rewriteRule[$newIndex]=$rewrite;
        $this->rewriteRuleIndex=$newIndex;
    }
    public function getRewrite()
    {
        return $this->rewriteRule[$this->rewriteRuleIndex];
    }
    public function setServiceMap($map)
    {
        $newIndex = ($this->serviceMapIndex+1)%2;
        $this->serviceMap[$newIndex]=$map;
        $this->serviceMapIndex=$newIndex;
    }
    public function getServiceMap()
    {
        return $this->serviceMap[$this->serviceMapIndex];
    }
    /**
     * 获取对应服务的实际地址，返回两个
     * @param string $serviceCmd
     * @param int $timestamp Description
     * @return \Sys\Config\ServiceLocation
     */
    public function getRouteFor($serviceCmd0,$timestamp)
    {

        $serviceCmd = isset($this->rewriteRule[$this->rewriteRuleIndex][$serviceCmd0])?$this->rewriteRule[$this->rewriteRuleIndex][$serviceCmd0]:$serviceCmd0;

        $pos = strpos($serviceCmd, '/',1);
        $m  = trim(substr($serviceCmd, 0,$pos),'/');
        
        if(!isset($this->serviceMap[$this->serviceMapIndex][$m])){
            return null;
        }else{
            $location = $this->loadBalance->chose($this->serviceMap[$this->serviceMapIndex][$m],$m,$timestamp);
            if($location!=null){
                $location->cmd=$serviceCmd;
                return $location;
            }else{
                return null;
            }
        }
    }
    
    public function markNodeDown($ip,$port,$timestamp)
    {
        $this->loadBalance->markNodeDown($ip, $port, $timestamp);
    }
    public function loadbalanceStatus()
    {
        return $this->loadBalance->status();
    }
}
