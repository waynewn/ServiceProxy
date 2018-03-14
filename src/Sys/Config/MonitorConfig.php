<?php
namespace Sys\Config;

/**
 * 监控相关配置
 *
 * @author wangning
 */
class MonitorConfig {
    /**
     * 用到的微服务的URI，格式： array( mail=>/MailService/Broker/sendAsync, )
     * @var array 
     */
    public $services=array();
    /**
     * 监控管理的用户组
     *  array('ErrorNode'=>'a@b.cn,b@b.cn(自定义的用户列表格式)')
     * @var array  
     */
    public $usersgroup=array();
}
