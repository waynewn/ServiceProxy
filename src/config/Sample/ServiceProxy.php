<?php
//如果系统里已经存在名为 ServiceProxy 的项目了，这里改下
define('MICRO_SERVICE_MODULENAME', 'ServiceProxy');
//投递失败的节点，多久后再次尝试
define('EXPIRE_SECONDS_NODE_FAILED', 300);
//最大可fork的task任务数
define('MAX_TASK_NUM',100);
