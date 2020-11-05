---
layout: post
title: "mysql 查看连接数"
date: 2020-11-05 16:49:00 +0800
comments: false
categories:
- 2020
- 2020~11
- tools
- tools~mysql
tags:
---

#### 查看当前所有连接的详细资料:
```
	mysqladmin -uadmin -p processlist
```

#### 只查看当前连接数(Threads就是连接数.):
```
	mysqladmin -uadmin -p status
```

#### 连接进程

```
	mysql -uroot -p

	mysql> show full processlist 查看所有连接进程，注意查看进程等待时间以及所处状态 是否locked


	mysql> show variables like '%_connections';

	max_user_connections	这个就是单用户的连接数
	max_connections		这个是全局的限制连接数

	mysql> set global max_connections = 152;
	
```
