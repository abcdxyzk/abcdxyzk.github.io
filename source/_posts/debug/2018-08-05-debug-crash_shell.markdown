---
layout: post
title: "crash执行shell脚本"
date: 2018-08-05 23:39:00 +0800
comments: false
categories:
- 2018
- 2018~08
- debug
- debug~kdump、crash
tags:
---

crash执行shell脚本

```
	crash> kmem -S TCP > /tmp/slabinfo

	[root@vm1 ~]# cat /tmp/slabinfo | grep '\[fff' | awk -F[ '{print $2}' | awk -F] '{print "sock "$1" | grep skc_portpair >> /tmp/sock"}' > /tmp/sock.sh

	crash> < /tmp/sock.sh

```
