---
layout: post
title: "Composer 安装与使用"
date: 2021-06-20 16:31:00 +0800
comments: false
categories:
- 2021
- 2021~06
- language
- language~php
tags:
---

https://www.runoob.com/w3cnote/composer-install-and-usage.html

Composer 是 PHP 的一个依赖管理工具。我们可以在项目中声明所依赖的外部工具库，Composer 会帮你安装这些依赖的库文件，有了它，我们就可以很轻松的使用一个命令将其他人的优秀代码引用到我们的项目中来。

Composer 默认情况下不是全局安装，而是基于指定的项目的某个目录中（例如 vendor）进行安装。

Composer 需要 PHP 5.3.2+ 以上版本，且需要开启 openssl。

Composer 可运行在 Windows 、 Linux 以及 OSX 平台上。


#### 安装

```
	php -r "copy('https://install.phpcomposer.com/installer', 'composer-setup.php');"
	php composer-setup.php

	All settings correct for using Composer
	Downloading...

	Composer (version 1.6.5) successfully installed to: /root/composer.phar
	Use it: php composer.phar
```

移动 composer.phar，这样 composer 就可以进行全局调用：

```
	mv composer.phar /usr/local/bin/composer
```

切换为国内镜像：
```
	composer config -g repo.packagist composer https://mirrors.aliyun.com/composer/
```

更新 composer：

```
	composer selfupdate
```

