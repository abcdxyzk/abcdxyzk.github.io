---
layout: post
title: "udp vpn, 代理dota2"
date: 2021-04-08 21:29:00 +0800
comments: false
categories:
- 2021
- 2021~04
- kernel
- kernel~vpn
tags:
- vpn
---

### 问题

手机端MTU有时是1300？？？，改大了似乎tcp不通，改成1350似乎没问题。
ifconfig rmnet_data0 mtu 1350

### 方案

用udp fullnat，起始点在包的末尾加上最终目标IP，中间点以目标IP为VIP建立session，最后点去除额外加的数据，然后发给目标IP

### 方法一

#### client(dota2):
```
	ip route add default via 192.168.56.3 table 1
	ip rule add to 103.10.0.0/16 table 1
	ip rule add to 106.52.0.0/16 table 1
	ip rule add to 139.45.0.0/16 table 1
	ip rule add to 146.66.0.0/16 table 1
	ip rule add to 153.254.0.0/16 table 1
	ip rule add to 155.133.0.0/16 table 1
	ip rule add to 162.254.0.0/16 table 1
	ip rule add to 185.25.0.0/16 table 1
	ip rule add to 190.217.0.0/16 table 1
	ip rule add to 205.185.0.0/16 table 1
	ip rule add to 205.196.0.0/16 table 1
```
client不用安装模块，通过策略路由将包导到本地虚拟机, 发出包 192.168.8.100:12345 --> 153.254.86.167:27023

#### start_point(local vm):
```
	insmod ip_vs.ko; insmod ip_vs_wrr.ko
	echo 192.168.8.103 > /proc/sys/net/ipv4/vs/laddr_v4_str
	echo 106.52.108.171 > /proc/sys/net/ipv4/vs/default_dest_v4_str
```
收到包: 192.168.8.100:12345 --> 153.254.86.167:27023
发出包: 192.168.8.103:5001 --> 106.52.108.171:27023 在包的末尾加上153.254.86.167
收包和发包可以不在同一网卡

#### middle_point(gz):
```
	insmod ip_vs.ko; insmod ip_vs_wrr.ko
	echo 101.32.220.184 > /proc/sys/net/ipv4/vs/default_dest_v4_str
```
单纯转发

#### end_point(hk):
```
	insmod ip_vs.ko; insmod ip_vs_wrr.ko
```
取出包的末尾153.254.86.167作为rs，去除末尾自己加的数据，然后转发


### 用法二：
#### client(dota2):
```
	insmod ip_vs.ko local_out=1
	echo 119.29.157.106 > /proc/sys/net/ipv4/vs/default_dest_v4_str
```
在包过local_out时修改目标IP为middle_ip、末尾加数据、建立session，session按cip:cport --> middle_ip:dport建，所以两条连接的cport、dport相同而dip不同则会冲突(TODO)
只换ip，没有重新路由，所以dip和middle_ip的出口需要在一张网卡上

#### middle_point(gz):
```
	insmod ip_vs.ko; insmod ip_vs_wrr.ko
	echo 101.32.220.184 > /proc/sys/net/ipv4/vs/default_dest_v4_str
```

#### end_point(hk):
```
	insmod ip_vs.ko; insmod ip_vs_wrr.ko
```

