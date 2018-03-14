<?php
namespace Sys\Util\Email;
require __DIR__.'/Impl.php';
/**
 * smtp发送邮件
 * SmtpSSL::factory('user=xxx&pass=xxxx&server=smtp.exmail.qq.com[&port=465]')
        ->sendTo(字符串或数组方式提供的收件人地址, '内容', '标题')
 * @author wangning
 */
class SmtpSSL {
    /**
     * @param string $msgCtrlClassName 类的名字
     * @param string $iniString 类的初始化参数,格式 var1=123&var2=245
     * @return \Sys\Util\Email\SmtpSSL
     */
    public static function factory($iniString)
    {
        $msgCtrlClassName = get_called_class();
        $obj = new $msgCtrlClassName;
        $obj->init($iniString);
        return $obj;
    }
    protected $_ini;
    protected function init($iniString)
    {
        parse_str($iniString,$this->_ini);
        if(empty($this->_ini['port'])){
            $this->_ini['port']=465;
        }
        return $this;
    }

    /**
     * 向指定（单个或一组）用户发消息
     * @param mixed $user 单用户用字符串，多个用户以数组方式提供
     * @param string $content 内容
     * @param string $title 标题，有些情况不需要
     * @throws \ErrorException
     * @return string 消息发送结果,成功返回{"error":0}
     */
    public function sendTo($user,$content,$title=null,$args=null)
    {
        $mail = new Impl();
        //$mail->setServer("smtp@126.com", "XXXXX@126.com", "XXXXX"); //设置smtp服务器，普通连接方式
        $mail->setServer($this->_ini['server'], $this->_ini['user'], $this->_ini['pass'], 465, true); //设置smtp服务器，到服务器的SSL连接
        $mail->setFrom($this->_ini['user']); //设置发件人
        if(is_array($user)){
            foreach ($user as $u){
                $mail->setReceiver($u); //设置收件人，多个收件人，调用多次
            }
        }else{
            $mail->setReceiver($user);
        }
        //$mail->setCc("XXXX"); //设置抄送，多个抄送，调用多次
        //$mail->setBcc("XXXXX"); //设置秘密抄送，多个秘密抄送，调用多次
        //$mail->addAttachment("XXXX"); //添加附件，多个附件，调用多次
        $mail->setMail($title, $content); //设置邮件主题、内容
        $ret = $mail->sendMail(); //发送
        if($ret==false){
            throw new \ErrorException($ret);
        }else{
            return '{"error":0}';
        }
    }
}
