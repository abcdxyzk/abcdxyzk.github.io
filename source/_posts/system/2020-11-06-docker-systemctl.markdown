---
layout: post
title: "docker 容器里使用systemctl命令"
date: 2020-11-06 17:11:00 +0800
comments: false
categories:
- 2020
- 2020~11
- system
- system~docker
tags:
---

#### error 1

bash: service: command not found

```
	yum install initscripts -y
```

#### error 2

System has not been booted with systemd as init system (PID 1). Can't operate.

容器的命令：
```
	docker run -d --name centos_1 -it  centos:latest /bin/bash
```
需要修改为
```
	docker run -tid --name centos_1 --privileged=true centos:latest /sbin/init
```
也就是加--privileged=true，修改/binbash  为/sbin/init


