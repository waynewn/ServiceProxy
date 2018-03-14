<?php
//默认监听端口，设为0的话，使用启动时参数
define('MAILSERVICE_PORT',8008);
//最大异步任务数
define('MAILSERVICE_MAX_TASK',10);
//最大同时接收请求数量（同步异步都算）
define('MAILSERVICE_MAX_REQUEST',10);
//module & ctrl 名称定义
define('MAILSERVICE_MODULE_CTRL','/MailService/Broker');
//邮箱设置：服务器地址
define('MAILSERVICE_MAILSERVER_ADDR','smtp.exmail.qq.com');
//邮箱设置：服务器端口
define('MAILSERVICE_MAILSERVER_SSLPORT',465);
//邮箱设置：发送者账户名
define('MAILSERVICE_SENDER_USER','');
//邮箱设置：发送者账户密码
define('MAILSERVICE_SENDER_PASSWORD','');