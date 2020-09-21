---
layout: post
title: "apache2 访问认证"
date: 2020-09-20 14:12:00 +0800
comments: false
categories:
- 2020
- 2020~09
- tools
- tools~apache2
tags:
---

#### 创建密码
```
	htpasswd -c squid.pwd admin

	chown www-data:www-data squid.pwd
```


#### 修改 /etc/apache2/apache2.conf
```
	<Location /cgi-bin/cachemgr.cgi>
		AuthType Basic
		AuthName "admin"
		AuthUserFile  /etc/squid/squid.pwd
		require valid-user
	</Location>
```
