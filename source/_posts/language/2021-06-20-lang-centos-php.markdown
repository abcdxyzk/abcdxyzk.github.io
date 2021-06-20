---
layout: post
title: "Centos7安装PHP7"
date: 2021-06-20 16:18:00 +0800
comments: false
categories:
- 2021
- 2021~06
- language
- language~php
tags:
---

https://www.jianshu.com/p/1e23aba0a164

```
	 # 安装EPEL yum存储库
	 yum install epel-release -y
	 # 安装Remi存储库
	 rpm -Uvh http://rpms.famillecollet.com/enterprise/remi-release-7.rpm

	 # 安装 PHP 7.2
	 yum --enablerepo=remi-php73 install php
	 # 安装 PHP 7.3
	 yum --enablerepo=remi-php72 install php
	 # 安装 PHP 7.4 
	 yum --enablerepo=remi-php74 install php php-zip php-xml
```
