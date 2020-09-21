---
layout: post
title: "squid 开启cgi-bin/cachemgr.cgi"
date: 2020-09-20 14:23:00 +0800
comments: false
categories:
- 2020
- 2020~09
- tools
- tools~squid
tags:
- squid
---

#### start

[apache2 支持cgi](/blog/2020/09/20/tools-apache2-cgi/)

(可选)[apache2 开启认证](/blog/2020/09/20/tools-apache2-auth/)

sudo apt-get install squid-cgi

文件就在 /usr/lib/cgi-bin/ 下面，和 apache2 目录一致，不需要cp
```
	vim /etc/squid/cachemgr.conf
	localhost
	换成
	localhost:port
```

```
	vim /etc/squid/squid.conf
	注释掉这两行
	#http_access allow localhost manager
	#http_access deny manager
	添加这两行
	acl manager proto cache_object
	http_access allow manager
```

squid -k reconfigure

#### cachemgr_passwd

vim /etc/squid/squid.conf

cachemgr_passwd none all # 所有用户开启所有权限

// cachemgr_passwd 123456 all 不生效 ???

squid -k reconfigure

web 打开 http://ip/cgi-bin/cachemgr.cgi 就能查看、操作一些squid功能了

用户名：manager 或 空

密码：空

#### ubuntu 18.04

18.04 的 squid 版本3.5.27-1ubuntu1.8 好像有问题，点击 Current Squid Configuration squid就重启。

换成 16.04 的 3.5.12-1ubuntu7.13 就没问题了。

http://security.ubuntu.com/ubuntu/pool/main/s/squid3/squid_3.5.12-1ubuntu7.13_amd64.deb

http://security.ubuntu.com/ubuntu/pool/universe/s/squid3/squid-cgi_3.5.12-1ubuntu7.13_amd64.deb

http://security.ubuntu.com/ubuntu/pool/main/s/squid3/squid-common_3.5.12-1ubuntu7.13_all.deb

