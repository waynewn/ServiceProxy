<?php
namespace Sys\Log;
/**
 * Description of Txt
 *
 * @author wangning
 */
class Txt {
    /**
     * 
     * @param \Sys\Config\LogConfig $config
     */
    public function __construct($config,$prefix) {
        $this->setConfig($config);
        $this->prefix=$prefix;
    }
    protected $prefix='';
    /**
     * 配置
     * @var \Sys\Config\LogConfig 
     */
    protected $config;
    /**
     * 实际负责记录日志
     * @param string $type
     * @param string $str
     */
    protected function _log($type,$str)
    {
        if(empty($this->config->root)){
            error_log("{$this->prefix}【{$type}】".$str);
        }else{
//            swoole_async_writefile(
//                    $this->config->root."/{$this->prefix}_{$type}_".date('Ymd').'.log',
//                    ''.date('m-d H:i:s').' '.$str."\n",
//                    null,
//                    FILE_APPEND
//                    );
            file_put_contents($this->config->root."/{$this->prefix}_{$type}_".date('Ymd').'.log', ''.date('m-d H:i:s').' '.$str."\n",FILE_APPEND);
        }
    }
    /**
     * 
     * @param \Sys\Config\LogConfig  $conf
     */
    public function setConfig($conf)
    {
        $this->config = $conf;
    }
    /**
     * 管理日志
     * @param type $cmd
     * @param type $args
     * @return type
     */
    public function managelog($cmd,$args=null)
    {
        return $this->_log('manage', $cmd.' '.(is_array($args)?json_encode($args):$args));
    }
    /**
     * 系统运行相关（比如：命令通讯）日志
     * @param type $path
     * @param type $evt
     * @param type $msg
     * @return type
     */
    public function syslog($cmd,$args=null)
    {
        return $this->_log('sys', $cmd.' '.(is_array($args)?json_encode($args):$args));
    }
    
    public function taskCreateFailed($data,$errMsg)
    {
        return $this->_log('sys', "Error: $errMsg on create task, ".(is_array($data)?json_encode($data):$data));
    }
    public function execNodeCmdFailed($ip,$nodeCmd,$errMsg)
    {
        return $this->_log('sys', "Error: exec $nodeCmd failed on $ip errMsg = ".$errMsg);
    }
    /**
     * rpc调用日志
     * @param type $path
     * @param type $service
     * @param type $tryServer
     * @param type $arg
     * @return type
     */
    public function proxylogFirstTry($md5FLG,$serviceapi,$fromIP,$toIP,$toPort)
    {
        return $this->_log('proxy', "$md5FLG firstTry $fromIP -> $toIP:$toPort/$serviceapi");
    }
    public function proxylogTryMore($md5FLG,$serviceapi,$fromIP,$toIP,$toPort)
    {
        return $this->_log('proxy', "$md5FLG nextTry $fromIP -> $toIP:$toPort/$serviceapi");
    }
    public function proxylogArgs($md5FLG,$args)
    {
        foreach($args as $k=>$v){
            $this->_log('proxy', "$md5FLG $k = ". (is_scalar($v)?$v: json_encode($v)));
        }
        return;
    }   
    public function proxylogFirstErr($md5FLG,$serviceapi,$fromIP,$toIP,$toPort,$dur,$ret)
    {
        $this->_log('proxy', "$md5FLG $fromIP -> $toIP:$toPort/$serviceapi CONSUMING_TIME = $dur" );
        if(is_scalar($ret)){
            if(sizeof($ret)>5000){
                $this->_log('proxy', "$md5FLG ErrFirst = (first1k-base64ed)". base64_encode(substr($ret,0,1000)));
            }else{
                $this->_log('proxy', "$md5FLG ErrFirst = ". $ret);
            }
        }else{
            $this->_log('proxy', "$md5FLG ErrFirst = ". json_encode($ret));
        }
        
        return;
    }     
    public function proxylogResult($md5FLG,$serviceapi,$fromIP,$toIP,$toPort,$dur,$ret)
    {
        $this->_log('proxy', "$md5FLG $fromIP -> $toIP:$toPort/$serviceapi CONSUMING_TIME = $dur" );
        if(is_scalar($ret)){
            if(sizeof($ret)>5000){
                $this->_log('proxy', "$md5FLG RESULT = (first1k-base64ed)". base64_encode(substr($ret,0,1000)));
            }else{
                $this->_log('proxy', "$md5FLG RESULT = ". $ret);
            }
        }else{
            $this->_log('proxy', "$md5FLG RESULT = ". json_encode($ret));
        }
        
        return;
    }    
    /**
     * 记录问题节点
     * @param type $serviceapi
     * @param type $node
     * @return type
     */
    public function errorNode($serviceapi,$fromIP,$toIP,$toPort)
    {
        return $this->_log('err', "$serviceapi failed on $fromIP -> $toIP:$toPort");
    }
    /**
     * trace
     * @param type $str
     * @return type
     */
    public function trace($str)
    {
        $ee = new \Exception();
        $arr = $ee->getTrace();
        $r = $arr[1];
        return $this->_log('trace', $r['class'].'->'.$r['function'].'(): '.$str);
    }
}
