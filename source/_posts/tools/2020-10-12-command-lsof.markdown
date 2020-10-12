---
layout: post
title: "lsof"
date: 2020-10-12 15:48:00 +0800
comments: false
categories:
- 2020
- 2020~10
- tools
- tools~command
tags:
---

### lsof查看端口被哪些程序在使用

```
	lsof -i TCP:port -n
	lsof -i UDP:port -n
	lsof -i :port -n
	lsof -i tcp:1521 -n
```

### 查看连接创建时间

```
	netstat -npt | grep port
	tcp        0      0 ::ffff:192.168.251.43:51520 ::ffff:192.168.110.231:8998 ESTABLISHED 32439/java  

	lsof -p pid | grep port
	java    32439 root  118u  IPv6          165707367      0t0       TCP SC-HOST-43:51518->192.168.110.231:8998 (ESTABLISHED) 
	java    32439 root  126u  IPv6          165707404      0t0       TCP SC-HOST-43:51520->192.168.110.231:8998 (ESTABLISHED)

	注意到118u和126u是这两个连接的文件名，然后去ll /proc/pid/fd/118，就可以看到这个连接的建立时间了。
```

### 查看进程启动路径

```
	ls -l /proc/pid/xx
```

