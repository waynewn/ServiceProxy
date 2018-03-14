#环境安装

## php 安装

网上资料很多，这里不再赘述。

需要 simple_xml扩展

## swoole安装

如果不是源码安装，编译安装swoole.so 需要额外安装php-devel。生产环境可以直接使用编译好的swoole.so文件。

1. 网上下载对应版本源码，写文档时使用的地址是：
https://github.com/swoole/swoole-src/archive/v2.1.0.tar.gz

2. 解压后到src目录下

		cd swoole-src-swoole-1.7.6-stable/

		phpize
		./configure --enable-coroutine
		make
		make install

3. 开启php动态加载（不要静态加载）

如果不知道php.ini的位置，使用命令“php -i | grep php.ini”查看

在php.ini里找到enable_dl设置项，设置为"On" 

