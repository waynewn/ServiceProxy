<?php
namespace Sys\Config;
include __DIR__.'/../LoadBalance/RoundRobin.php';
/**
 * 代理节点的配置项
 *
 * @author wangning
 */
class ProxyConfig {
    public $envIni=array();    
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
    public $nodename=array();
    public $configVersion='0.0.0';
    /**
     * 
     * @param \Sys\Config\ProxyConfig $newObj
     */
    public function copyFrom($newObj)
    {
        foreach($newObj->envIni as $k=>$v){
            if(!defined($k)){
                define($k,$v);
            }
        }
        if($this->loadBalance===null){
        	$this->loadBalance = new \Sys\LoadBalance\RoundRobin();
        }
        foreach($newObj->nodename as $k=>$v){
            $this->nodename[$k]=$v;
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
    public function workAsGlobal()
    {
        $this->rewriteRuleIndex = new \swoole_table(1);
        $this->rewriteRuleIndex->column('index', \swoole_table::TYPE_INT, 4);
        $this->rewriteRuleIndex->create();
        $this->rewriteRuleIndex->set('0',array('index'=>0));
        
        $this->serviceMapIndex = new \swoole_table(1);
        $this->serviceMapIndex->column('index', \swoole_table::TYPE_INT, 4);
        $this->serviceMapIndex->create();
        $this->serviceMapIndex->set('0',array('index'=>0));
        
        $nodeLen = MAX_NODE_PER_SERVICE * 40;
        
        for($i=0;$i<2;$i++){
            $this->rewriteRule[$i] = new \swoole_table(MAX_REWRITE);
            $this->rewriteRule[$i]->column('to', \swoole_table::TYPE_STRING, 120);
            $this->rewriteRule[$i]->create();
            
            $this->serviceMap[$i] = new \swoole_table(MAX_SERVICES);
            $this->serviceMap[$i]->column('list', \swoole_table::TYPE_STRING, $nodeLen);
            $this->serviceMap[$i]->create();
        }
        
        $this->loadBalance->workAsGlobal();
    }
    public function setRewrite($rewrite)
    {
        $newIndex = ($this->getRewriteIndex()+1)%2;
        if(is_scalar($this->rewriteRuleIndex)){
            $this->rewriteRule[$newIndex]=$rewrite;
            $this->rewriteRuleIndex=$newIndex;
        }else{
            \Sys\Util\Funcs::emptySwooleTable($this->rewriteRule[$newIndex]);
            
            foreach($rewrite as $from=>$to){
                $this->rewriteRule[$newIndex]->set($from,array('to'=>$to));
            }
            $this->rewriteRuleIndex->set('0',array('index'=>$newIndex));
        }
    }
    public function getRewrite($find=null)
    {
        if(is_scalar($this->rewriteRuleIndex)){
            if($find===null){
                return $this->rewriteRule[$this->rewriteRuleIndex];
            }else{
                return isset($this->rewriteRule[$this->rewriteRuleIndex][$find])?$this->rewriteRule[$this->rewriteRuleIndex][$find]:$find;
            }
        }else{
            $index = $this->rewriteRuleIndex->get('0','index');
            if($find===null){
                $ret = array();
                foreach($this->rewriteRule[$index] as $k=>$r){
                    $ret[$k]=$r['to'];
                }
                return $ret;
            }else{
                $tmp = $this->rewriteRule[$index]->get($find,'to');
                return empty($tmp)?$find:$tmp;
            }
        }
    }
    public function getRewriteIndex()
    {
        if(is_scalar($this->rewriteRuleIndex)){
            return $this->rewriteRuleIndex;
        }else{
            $this->rewriteRuleIndex->get('0','index');
        }
    }
    public function setServiceMap($map)
    {
        if(is_scalar($this->serviceMapIndex)){
            $newIndex = ($this->serviceMapIndex+1)%2;
            $this->serviceMap[$newIndex]=$map;
            $this->serviceMapIndex=$newIndex;
        }else{
            $newIndex = ($this->serviceMapIndex->get('0','index')+1)%2;
            \Sys\Util\Funcs::emptySwooleTable($this->serviceMap[$newIndex]);
            
            foreach($map as $from=>$r){
                $this->serviceMap[$newIndex]->set($from,array('list'=> json_encode($r)));
            }
            $this->serviceMapIndex->set('0',array('index'=>$newIndex));
        }
    }
    public function getServiceMap($find=null)
    {
        if(is_scalar($this->serviceMapIndex)){
            if($find===null){
                return $this->serviceMap[$this->serviceMapIndex];
            }elseif(isset($this->serviceMap[$this->serviceMapIndex][$find])){
                return $this->serviceMap[$this->serviceMapIndex][$find];
            }else{
                return null;
            }
        }else{
            $index = $this->serviceMapIndex->get('0','index');
            if($find===null){
                $ret = array();
                foreach($this->serviceMap[$index] as $k=>$r){
                    $ret[$k] = json_decode($r['list'],true);
                }
                return $ret;
            }else{
                $tmp = $this->serviceMap[$index]->get($find);
                if(empty($tmp)){
                    return null;
                }else{
                    return json_decode($tmp,true);
                }
            }
        }
    }
    /**
     * 获取对应服务的实际地址，返回两个
     * @param string $serviceCmd0
     * @param int $timestamp Description
     * @return \Sys\Config\ServiceLocation
     */
    public function getRouteFor($serviceCmd0,$timestamp)
    {
        $serviceCmd = $this->getRewrite($serviceCmd0);

        $pos = strpos($serviceCmd, '/',1);
        $m  = trim(substr($serviceCmd, 0,$pos),'/');
        
        $serviceMap = $this->getServiceMap($m);
        if(empty($serviceMap)){
            return null;
        }else{
            $location = $this->loadBalance->chose($serviceMap,$m,$timestamp);
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
