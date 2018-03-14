<?php
namespace Sys\Config;

/**
 * 实际请求位置
 *
 * @author wangning
 */
class ServiceLocation {
    public $ip;
    public $port;
    public $cmd;
    public $retry=0;
}
