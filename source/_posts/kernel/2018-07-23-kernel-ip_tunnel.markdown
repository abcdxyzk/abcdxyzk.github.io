---
layout: post
title: "IPIP实现IP隧道"
date: 2018-07-23 02:32:00 +0800
comments: false
categories:
- 2018
- 2018~07
- kernel
- kernel~net
tags:
---

https://blog.csdn.net/kkdelta/article/details/39611061

IPIP实现IP隧道的简单示例

两台主机，A和B，每台主机由两块网卡，其中eth0在同一个网段，能够互相连通。

A的eth1和B的eth1分别在两个不同的网段。

A: eth0:192.168.9.5 eth1:192.168.8.5

B: eth0:192.168.9.6 eth1:192.168.10.6

A:
```
	ip tun add lxT mode ipip remote 192.168.9.6 local 192.168.9.5
	ip link set lxT up
	ip add add 192.168.200.1 brd 255.255.255.255 peer 192.168.200.2 dev lxT
	ip ro add 192.168.200.0/24 via 192.168.200.1
```
B：
```
	ip tun add lxT mode ipip remote 192.168.9.5 local 192.168.9.6
	ip link set lxT up
	ip add add 192.168.200.2 brd 255.255.255.255 peer 192.168.200.1 dev lxT
	ip ro add 192.168.200.0/24 via 192.168.200.2
```

在A机器添加路由信息，指定到192.168.10.6通过lxT
```
	ip ro add 192.168.10.6/32 dev lxT
```

在B机器添加路由信息，指定到192.168.8.5通过lxT
```
	ip ro add 192.168.8.5/32 dev lxT
```

这样 192.168.8.5 和 192.168.10.6 就可以相互ping通了

#### 部分参数
ttl N 	设置进入通道数据包的TTL为N。N是一个1—255之间的数字。0是一个特殊的值，表示这个数据包的TTL值是继承(inherit)的。ttl参数的缺省值是：inherit。

tos T或者dsfield T 	设置进入通道数据包的TOS域，缺省是inherit。

mode MODE 	设置通道模式。有效的模式包括：ipip、sit和gre。

nopmtudisc 	在这个通道上禁止路径最大传输单元发现( Path MTU Discovery)。默认情况下，这个功能是打开的。注意：这个选项和固定的ttl是不兼容的，如果使用了固定的ttl参数，系统会打开路径最大传输单元发现( Path MTU Discovery)功能。

### ip tunnel gw

#### CLIENT:
```
	ifconfig eth1 11.0.0.20/24
	route add -net 14.0.0.0/24 gw 11.0.0.1 dev eth1
```

#### RS:
```
	ifconfig eth0 192.168.1.191/24
	modprobe ipip # ip tun add tunl0 mode ipip remote any local any
	ip link set tunl0 mtu 1480 up
	ifconfig tunl0:0 14.0.0.1/24

	ip tun add tunl1 mode ipip remote 12.0.0.1 local 12.0.0.102 dev eth1          # better
	#ip tun add tunl1 mode ipip remote 192.168.1.102 local 192.168.1.191 dev eth0  # upload err
	ip link set tunl1 mtu 1480 up

	ip rule add from 14.0.0.1 table 1
	ip route add table 1 default dev tunl1

	find /proc/ -name rp_filter -exec cat {} \;
	find /proc/ -name rp_filter -exec sh -c "echo 0 > {}" \;
```

#### GW:
```
	find /proc/ -name rp_filter -exec cat {} \;
	find /proc/ -name rp_filter -exec sh -c "echo 0 > {}" \;

	echo 1 > /proc/sys/net/ipv4/ip_forward

	modprobe ipip # ip tun add tunl0 mode ipip remote any local any
	ip link set tunl0 mtu 1480 up

	ip tun add tunl1 mode ipip remote 192.168.1.191 local 192.168.1.102 dev enp3s0     # better
	#ip tun add tunl1 mode ipip remote 12.0.0.102 local 12.0.0.1 dev enx00e04b367c0c    # upload err?
	ip link set tunl1 mtu 1480 up

	ip rule add from 11.0.0.20 table 1
	ip route add table 1 default dev tunl1
```

