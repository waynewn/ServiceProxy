<?php
namespace Sys;
use Sys\Util as util;
include __DIR__.'/CenterAlert.php';
include __DIR__.'/Coroutione/Clients.php';

use Sys\Coroutione\Clients as http_clients;
/**
 * 中央控制
 * todo ip限制
 * @author wangning
 */
class CenterBase extends CenterAlert{
    /**
     *
     * @var \Sys\Log\Txt
     */
    protected $log;
    protected $configFilePath;
    public function __construct($confFilePath) {
        $this->configFilePath = $confFilePath;
        $this->config=new \Sys\Config\CenterConfig();
        $this->log = null;
        $this->clients = new \Sys\Coroutione\Clients();
    }

    public function onListenStart()
    {
        $this->log->syslog('center starting...');
    }
    public function reloadConfig()
    {
        $tmp = \Sys\Config\XML2CenterConfig::parse($this->configFilePath);
        if(!empty($tmp)){
            $this->config->copyFrom($tmp);
            if($this->log===null){
                $this->log=new \Sys\Log\Txt($this->config->LogConfig,'CENTER');
            }
            return true;
        }else{
            return false;
        }
    }
    public function getMyIp()
    {
        return $this->config->centerIp;
    }
    public function getMyPort()
    {
        return $this->config->centerPort;
    }
    /**
     *
     * @var \Sys\Config\CenterConfig
     */
    public $config;
    /**
     * 获取指定proxy status 结果
     * 返回 array(
     *      'http://123.234.234.2:234/ServiceProxy/proxy/status'=>array(
     *          'startup' => '2018-02-28 11:53:36',
     *          'proxyip'=>'1.2.3.4',
     *          'configVersion' => '1.0.1',
     *          'request_time' => '2018-02-28 11:54:07',
     *          'nodelist' =>  array (
     *              'payment01' => 'ping命令执行结果',
     *              'payment02' => NULL,
     *          ),
     *          'proxy' =>array(
     *              'counter'=>array(
     *                  '分钟戳'=> '目标节点IP:port'=>计数
     *              ),
     *              'error'=>array(
     *                  '问题节点IP:port'=>array('timestamp'=>最后报错时间戳，Num=>连续失败次数)
     *              ),
     *          )
     *      ),
     * )
     * @param array $ips
     * @param int $skipNodeStatus 1：skip，0：withStatus
     * @return array  
     */
    protected function getProxiesStatus($ips,$skipNodeStatus=0)
    {
        $clients = \Sys\Coroutione\Clients::create(90);
        
        if(!is_array($ips)){
            throw new \ErrorException('ips of proxy for getProxiesStatus() should be array, given:'. var_export($ips,true));
        }
        foreach($ips as $ip){
            $tmp = \Sys\Config\ProxyConfig::factory($this->config->proxy[$ip]);
            $clients->addTask($tmp->myIp, $tmp->myPort,'/'.MICRO_SERVICE_MODULENAME.'/proxy/status?skipNodeStatus='.$skipNodeStatus);
        }
        
        $ret = $clients->getResultsAndFree();
        foreach ($ret as $k=>$v){
            $ret[$k] = json_decode($v,true);
        }
        return $ret;
    }
    /**
     * 反串行化还原出 ProxyConfig
     * @param string $proxyStr
     * @return \Sys\Config\ProxyConfig
     */
    protected function getProxyConfigObjFromStr($proxyStr)
    {
        $tmp = \Sys\Config\ProxyConfig::factory($proxyStr);
        $tmp->setServiceMap($this->config->getServiceMap());
        $tmp->LogConfig = $this->config->LogConfig;
        $tmp->monitorConfig = $this->config->monitorConfig;
        $tmp->setRewrite($this->config->getRewrite());
        return $tmp;
    }
}
