<?php
namespace Sys\Util;

/**
 * 工具函数集
 *
 * @author wangning
 */
class Funcs {
    public static function parseCmdArg($argv)
    {
        $cmd = array();
        $len = sizeof($argv);
        $usage="usage: php xxxx.php args\n"
                ."CMD in CENTER:\n\tphp center.php -t config-file       check file\n\t-c config-file       run center with config-file\n\t-worker num        worker-thread num,default is 5\n"
                ."CMD in PROXY:\n\tphp proxy.php -h center-server-ip -p center-server-port \n\t-worker num        worker-thread num,default is 5\n";
        if($argv[1]=='--help'){
            die($usage);
        }
        for($i=1;$i<$len;$i+=2){
            if(!empty($argv[$i+1])){
                $cmd[$argv[$i]]=$argv[$i+1];
            }else{
                die($usage);
            }
        }
        if(empty($cmd)){
            die($usage);
        }
        return $cmd;
    }

    public static function getIp($var)
    {
        $parts = explode('.',$var);
        if(sizeof($parts)!=4){
            throw new \ErrorException('invalid ip given:'.$var);
        }
        $ret= '';
        foreach($parts as $i){
            $i =$i-0;
            if($i>255 || $i<0 || !is_int($i)){
                throw new \ErrorException('invalid ip given:'.$var);
            }
            $ret .= '.'.$i;
        }
        return substr($ret,1);
    }
    
    public static function emptySwooleTable($obj)
    {
        $tmp=array();
        foreach($obj as $k=>$r){
            $tmp[]=$k;
        }
        foreach($tmp as $k){
            $obj->del($k);
        }
    }
}
