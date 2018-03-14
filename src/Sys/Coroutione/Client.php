<?php
namespace Sys\Coroutione;

/**
 * Description of Client
 *
 * @author wangning
 */
class Client {
    private $cli;
    public function __construct($ip,$port,$uriWithQueryString,$args4Post=null,$timeout=1) {
        $this->cli = new \Swoole\Coroutine\Http\Client($ip, $port);
        $headers = array(
//            'Host' => "localhost",
//            "User-Agent" => 'Chrome/49.0.2587.3',
//            'Accept' => 'text/html,application/xhtml+xml,application/xml',
//            'Accept-Encoding' => 'gzip',
        );
        $this->cli->set([ 'timeout' => $timeout]);//1秒超时
        $this->cli->setDefer();
        if(!empty($args4Post)){
            if(!is_array($args4Post)){
                $headers["Content-Type"]="application/json";
            }
            $this->cli->setHeaders($headers);
            $this->cli->post($uriWithQueryString,$args4Post);
        }else{
            $this->cli->setHeaders($headers);
            $this->cli->get($uriWithQueryString);
        }

        
        //$ret = $cli->body;
//        $this->log->trace("url:".$uriWithQueryString);
//        $this->log->trace("ret:".$ret);
//        $this->log->trace("askForOBJ=". var_export($cli,true));
//        $this->cli->close();
//        return $ret;
    }
    
    public function tryGetResultAndFree($isLastTry=false)
    {
        $ret = null;
        if($this->cli->statusCode){
            $this->cli->recv();
            if($this->cli->statusCode==200){
                $ret = $this->cli->body;
            }else{
                $ret = $this->cli->statusCode;
            }
        }else{
            if($isLastTry){
                $ret = 408;//timeout;
            }
        }
        if(!empty($ret)){
            $this->cli->close();
            $this->cli=null;
        }
        return $ret;
    }
}
