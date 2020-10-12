---
layout: post
title: "ubuntu 18.04增加/etc/rc.local"
date: 2020-10-12 20:25:00 +0800
comments: false
categories:
- 2020
- 2020~10
- system
- system~ubuntu
tags:
---

https://blog.csdn.net/qq_41782149/article/details/89001226

ubuntu18.04不再使用 inited 管理系统，改用 systemd

#### 1.实现原理

systemd 默认会读取 /etc/systemd/system 下的配置文件，该目录下的文件会链接 /lib/systemd/system/ 下的文件。一般系统安装完 /lib/systemd/system/ 下会有 rc-local.service 文件，即我们需要的配置文件。

#### 2.将 /lib/systemd/system/rc-local.service 链接到 /etc/systemd/system/ 目录下面来

```
	ln -fs /lib/systemd/system/rc-local.service /etc/systemd/system/rc-local.service

	修改文件内容

	sudo vim /etc/systemd/system/rc-local.service

	在文件末尾增加
	[Install]
	WantedBy=multi-user.target
	Alias=rc-local.service

```

#### 3. 创建/etc/rc.local文件

```
	sudo vim /etc/rc.local

	#!/bin/bash
	...
```
