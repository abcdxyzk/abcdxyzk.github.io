---
layout: post
title: "mysql conf"
date: 2018-10-28 17:12:00 +0800
comments: false
categories:
- 2018
- 2018~10
- tools
- tools~mysql
tags:
---

#### /etc/my.cnf


增加：
```
	[client]
	default-character-set=utf8

	[mysql]
	default-character-set=utf8

	[mysqld]
	init_connect='SET collation_connection = utf8_unicode_ci'
	init_connect='SET NAMES utf8'
	character-set-server=utf8
	collation-server=utf8_unicode_ci
	skip-character-set-client-handshake
```

