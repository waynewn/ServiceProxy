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
    public $headerTransfer=array();
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
        if(defined('HEADER_TRANSFER') && strlen(HEADER_TRANSFER)>0){
            $this->headerTransfer = explode(',', HEADER_TRANSFER);
        }
        if($this->loadBalance===null){
            $this->loadBalance = new \Sys\LoadBalance\RoundRobin();
            if(!is_scalar($this->rewriteRuleIndex)){
                $this->loadBalance->workAsGlobal();
            }
        }
        if(!empty($newObj->nodename)){
            $this->setNodename($newObj->nodename);
        }
        $this->centerIp = $newObj->centerIp;
        $this->centerPort = $newObj->centerPort;
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
    
    public function setNodename($arr)
    {
        if(is_scalar($this->rewriteRuleIndex)){
            foreach($arr as $k=>$v){
                $this->nodename[$k]=$v;
            }
        }else{
            if(is_array($this->nodename)){
                $this->nodename = new \swoole_table(MAX_SERVICES * MAX_NODE_PER_SERVICE);
                $this->nodename->column('name', \swoole_table::TYPE_STRING, 64);
                $this->nodename->create();
            }
            foreach($arr as $k=>$v){
                $this->nodename->set($k, array('name'=>$v));
            }
        }
    }
    
    public function getNodename($ipWithPort)
    {
        if(is_array($this->nodename)){
            if(isset($this->nodename['$ipWithPort'])){
                return $this->nodename['$ipWithPort'];
            }else{
                return $ipWithPort;
            }
        }else{
            $tmp = $this->nodename->get($ipWithPort,'name');
            if(!empty($tmp)){
                return $tmp;
            }else{
                return $ipWithPort;
            }
        }
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
                $tmp = $this->serviceMap[$index]->get($find,'list');
                if(empty($tmp)){
                    return null;
                }else{
					if(is_array($tmp)){
                        return $tmp;
                    }else{
                        return json_decode($tmp,true);
                    }
                }
            }
        }
    }
    /**
     * 获取对应服务的实际地址，返回两个
     * @param string $serviceCmd0
     * @param int $timestamp Description
     * @param int $num 首次尝试时获得的ip:port
     * @return array \Sys\Config\ServiceLocation
     */
    public function getRouteFor($serviceCmd0,$timestamp,$num=1)
    {
        $serviceCmd = $this->getRewrite($serviceCmd0);

        $pos = strpos($serviceCmd, '/',1);
        if($pos===false){
            $m = strtolower(trim($serviceCmd,'/'));
        }else{
            $m  = strtolower(trim(substr($serviceCmd, 0,$pos),'/'));
        }
        
        $serviceMap = $this->getServiceMap($m);
        if(empty($serviceMap)){
            return null;
        }else{
            $router2 = $this->loadBalance->chose($serviceMap,$m,$timestamp,$num);
            if($router2!=null){
                for($i=0;$i<$num;$i++){
                    if($router2[$i]){
                        $router2[$i]->cmd=$serviceCmd;
                    }
                }
                return $router2;
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
    public function proxyEnd($location){
        $this->loadBalance->onProxyEnd($location);
    }
    public function proxyStart($location){
        $this->loadBalance->onProxyStart($location);
    }
    public function proxyCounterReset()
    {
        return $this->loadBalance->proxyCounterReset();
    }
}
