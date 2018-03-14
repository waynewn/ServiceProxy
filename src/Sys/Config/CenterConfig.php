<?php
namespace Sys\Config;
/**
 * 管理中心配置
 *
 * @author wangning
 */
class CenterConfig {
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
    public $configVersion;
    public $centerIp;
    public $centerPort;
    /**
     * rewrite
     * @var array 
     */
    public $rewriteRule=array( /*'from'=>'to'*/);
    public $rewriteRuleIndex=0;
    /**
     * 记录都在哪里提供了对应的service module
     */
    public $serviceMap=array(
        /**
        'serviceModule名字'=>array(
            ['ip'=>'127.0.0.1','port'=>123],
            ['ip'=>'127.0.0.1','port'=>1234],
        ),
         */
    );
    /**
     * 记录临时关闭的service
     */
    public $serviceMapDeactive=array();
    public $serviceMapIndex=0;
    /**
     * center搜索功能专用
     * @var array  (serviceModule=>[nodename,nodename])
     */
    public $serviceInNode=array();
    /**
     * center用于根据名称获取node的ip位置
     * @var array  (nodename => [ip=>127.0.0.1, port=>1234])
     */
    public $nodeLocation=array();
    /**
     * 所有代理的配置列表
     * @var array (proxyIp=> configobj-string )
     */
    public $proxy=array();
    /**
     * 
     * @param CenterConfig $newobj
     */
    public function copyFrom($newobj)
    {
        $this->configVersion = $newobj->configVersion;
        $this->LogConfig = $newobj->LogConfig;
        $newobj->LogConfig = null;
        $this->monitorConfig = $newobj->monitorConfig;
        $newobj->monitorConfig = null;
        $this->centerIp=$newobj->centerIp;
        $this->centerPort=$newobj->centerPort;
        $this->setRewrite($newobj->getRewrite());
        $this->setServiceMap($newobj->getServiceMap());
        $this->serviceInNode = $newobj->serviceInNode;
        $this->nodeLocation = $newobj->nodeLocation;
        $this->serviceMapDeactive = $newobj->serviceMapDeactive;
        $this->proxy = $newobj->proxy;
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
}
