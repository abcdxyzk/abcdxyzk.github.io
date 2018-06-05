---
layout: post
title: "lvs fwmark 模式"
date: 2018-06-06 01:51:00 +0800
comments: false
categories:
- 2018
- 2018~06
- kernel
- kernel~base
tags:
---

http://blog.51cto.com/angus717/769577


persistent netfilter marked packet persistence 持久防火墙标记（在pre-routing链上打netfilter marked，而且该标记只在防火墙内部有效通常是0-99）

```
	[root@slave ~]# ipvsadm -C
	[root@slave ~]# iptables -t mangle -A PREROUTING -i eth0 -p tcp -d 172.16.8.120 --dport 80 -j MARK --set-mark 80
	[root@slave ~]# iptables -t mangle -A PREROUTING -i eth0 -p tcp -d 172.16.8.120 --dport 443 -j MARK --set-mark 80
	[root@slave ~]# ipvsadm -A -f 80 -s rr -p 1000
	[root@slave ~]# ipvsadm -a -f 80 -r 172.16.100.7 -g
	[root@slave ~]# ipvsadm -a -f 80 -r 172.16.100.6 -g
	[root@slave ~]# ipvsadm -Ln
	IP Virtual Server version 1.2.1 (size=4096)
	Prot LocalAddress:Port Scheduler Flags
	  -> RemoteAddress:Port           Forward Weight ActiveConn InActConn
	FWM  80 rr persistent 1000
	  -> 172.16.100.6:0               Route   1      0          0        
	  -> 172.16.100.7:0               Route   1      0          0 
```

