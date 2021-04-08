---
layout: post
title: "gre"
date: 2020-12-11 11:04:00 +0800
comments: false
categories:
- 2020
- 2020~12
- kernel
- kernel~vpn
tags:
- vpn
---

#### 1. GRE介绍

GRE隧道是一种IP-over-IP的隧道，是通用路由封装协议，可以对某些网路层协议的数据报进行封装，使这些被封装的数据报能够在IPv4/IPv6 网络中传输。

Tunnel 是一个虚拟的点对点的连接，提供了一条通路使封装的数据报文能够在这个通路上传输，并且在一个Tunnel 的两端分别对数据报进行封装及解封装。一个X协议的报文要想穿越IP网络在Tunnel中传输，必须要经过加封装与解封装两个过程。

要在Linux上创建GRE隧道，需要ip_gre内核模块，它是GRE通过IPv4隧道的驱动程序。

#### 2.

A 1.1.1.1, lo:0 10.1.2.3

B 2.2.2.2, lo:0 10.4.5.6

A 上面：
```
	ifconfig lo:0 10.1.2.3/24 up
	ip tunnel add gre1 mode gre remote 1.1.1.1 local 2.2.2.2 ttl 225 dev enp13s0
	ip addr add 10.1.2.3/24 peer 10.4.5.6/24 dev gre1
	ip link set dev gre1 up
	ip route add 10.4.5.6/24 dev gre1
```

B 上面：
```
	ifconfig lo:0 10.4.5.6/24 up
	ip tunnel add gre1 mode gre remote 2.2.2.2 local 1.1.1.1 ttl 225 dev enp13s0
	ip addr add 10.4.5.6/24 peer 10.1.2.3/24 dev gre1
	ip link set dev gre1 up
	ip route add 10.1.2.3/24 dev gre1
```

