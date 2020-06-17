---
layout: post
title: "mptcp使用"
date: 2020-05-28 17:08:00 +0800
comments: false
categories:
- 2020
- 2020~05
- kernel
- kernel~mptcp
tags:
---

### 编译

https://github.com/abcdxyzk/mptcp-v0.95

https://github.com/abcdxyzk/ubuntu-mptcp-v0.95

.config 配置

```
	diff --git a/.config b/.config
	index cf146c1c..f5be3370 100644
	--- a/.config
	+++ b/.config
	@@ -1054,6 +1054,11 @@ CONFIG_TCP_CONG_ILLINOIS=m
	 CONFIG_TCP_CONG_DCTCP=m
	 CONFIG_TCP_CONG_CDG=m
	 CONFIG_TCP_CONG_BBR=m
	+CONFIG_TCP_CONG_LIA=m
	+CONFIG_TCP_CONG_OLIA=m
	+CONFIG_TCP_CONG_WVEGAS=m
	+CONFIG_TCP_CONG_BALIA=m
	+# CONFIG_TCP_CONG_MCTCPDESYNC is not set
	 CONFIG_DEFAULT_CUBIC=y
	 # CONFIG_DEFAULT_RENO is not set
	 CONFIG_DEFAULT_TCP_CONG="cubic"
	@@ -1090,6 +1095,21 @@ CONFIG_IPV6_PIMSM_V2=y
	 CONFIG_IPV6_SEG6_LWTUNNEL=y
	 CONFIG_IPV6_SEG6_HMAC=y
	 CONFIG_NETLABEL=y
	+CONFIG_MPTCP=y
	+CONFIG_MPTCP_PM_ADVANCED=y
	+CONFIG_MPTCP_FULLMESH=y
	+CONFIG_MPTCP_NDIFFPORTS=m
	+CONFIG_MPTCP_BINDER=m
	+# CONFIG_MPTCP_NETLINK is not set
	+CONFIG_DEFAULT_FULLMESH=y
	+# CONFIG_DEFAULT_DUMMY is not set
	+CONFIG_DEFAULT_MPTCP_PM="fullmesh"
	+CONFIG_MPTCP_SCHED_ADVANCED=y
	+# CONFIG_MPTCP_BLEST is not set
	+CONFIG_MPTCP_ROUNDROBIN=m
	+CONFIG_MPTCP_REDUNDANT=m
	+CONFIG_DEFAULT_SCHEDULER=y
	+CONFIG_DEFAULT_MPTCP_SCHED="default"
	 CONFIG_NETWORK_SECMARK=y
	 CONFIG_NET_PTP_CLASSIFY=y
	 CONFIG_NETWORK_PHY_TIMESTAMPING=y
```

https://www.cnblogs.com/fenglt/p/8570343.html

### 路由配置

enp0s9: 192.168.2.5  gw 192.168.2.4

enp0s10: 192.168.3.5 gw 192.168.3.4

```
	ip rule add table 1 from 192.168.2.5
	ip route add 192.168.2.0/24 dev enp0s9 scope link table 1
	ip route add default via 192.168.2.4 dev enp0s9 table 1

	ip rule add table 2 from 192.168.3.5
	ip route add 192.168.3.0/24 dev enp0s10 scope link table 2
	ip route add default via 192.168.3.4 dev enp0s10 table 2
```

### fullmesh, ndiffports, binder

#### 工具

sudo apt-get install bison flex

https://github.com/abcdxyzk/iproute-mptcp

./configure

make


./ip/ip link set dev enp0s3 multipath off/on/backup # backup 不起作用？？

off命令是在MPTCP层面上的，并不是完全关闭该接口，而是控制MPTCP不去试图使用该网卡，换言之，当路由表指向该接口时，该接口还是会被使用的。

backup命令就是将该接口设置为备用模式，只有其他接口不可用时才会使用该接口。

#### fullmesh

两边IP都会建连，即建立 n*m 条连接

IP查看 cat /proc/net/mptcp_fullmesh


#### ndiffports 两边只在一组IP上建立多条连接。
```
	echo ndiffports > /proc/sys/net/mptcp/mptcp_path_manager
	echo 5 > /sys/module/mptcp_ndiffports/parameters/num_subflows # 总共建5条，即额外再建4条
```

#### binder ??


--------------------------

https://www.cnblogs.com/zhuting/p/5828988.html

http://multipath-tcp.org/pmwiki.php/Users/ConfigureMPTCP

### 参数

#### net.mptcp.mptcp_enabled
顾名思义，该变量控制MPTCP开关，实现MPTCP与传统TCP之间的切换。变量值为0或1（默认为1）。

#### net.mptcp.mptcp_checksum
该变量控制MPTCP传输层中数据序列号校验和（DSS-checksum）的开关，DSS-checksum主要和传输的可靠性相关，只要通信对端中有一端开启，就会执行。变量值为0或1（默认为1）。

#### net.mptcp.mptcp_syn_retries
设置SYN的重传次数。SYN里包含了MP_CAPABLE-option字段，通过该控制变量，SYN将不会包含MP_CAPABLE-option字段，这是为了处理会丢弃含有未知TCP选项的SYN的网络中间件。变量默认值为3。

#### net.mptcp.mptcp_debug
调试MPTCP，控制是否打印debug报告文件。

#### net.mptcp.mptcp_path_manager

MPTCP路径管理，有四个不同的配置值，分别是 default/fullmesh/ndiffports/binder。default/ndiffports/fullmesh分别选择单路、多路或者全路进行传输。其中单路是指跟传统TCP状态一样还是用单一的TCP子流进行传输，多路是当前所有TCP子流中用户选择x条子流数进行传输，全路是指将当前所有可用的TCP子流应用到网络传输中。而binder参考了文献 Binder: a system to aggregate multiple internet gateways in community networks。

#### net.mptcp.mptcp_scheduler
MPTCP子流调度策略，有default/roundrobin两个选项。default优先选择RTT较低的子流直到拥塞窗口满，roundrobin采用轮询策略。

-----------------------

https://www.cnblogs.com/lxgeek/p/4187164.html

### MPTCP 理解

MPTCP允许在一条TCP链路中建立多个子通道。当一条通道按照三次握手的方式建立起来后，可以按照三次握手的
方式建立其他的子通道，这些通道以三次握手建立连接和四次握手解除连接。这些通道都会绑定于MPTCP session，
发送端的数据可以选择其中一条通道进行传输。

MPTCP的设计遵守以下两个原则：

1.应用程序的兼容性，应用程序只要可以运行在TCP环境下，就可以在没有任何修改的情况下，运行于MPTCP环境。

2.网络的兼容性，MPTCP兼容其他协议。

MPTCP在协议栈中的位置如下所示：

![](/images/kernel/20200528-1.jpg)

#### 建立连接过程

![](/images/kernel/20200528-2.jpg)

如上图所示：MPTCP的第一个子通道的建立遵守TCP的三次握手，唯一的区别是每次发送的
报文段需要添加MP_CAPABLE的的TCP选项和一个安全用途的key。而下图是建立其他的子通道：

![](/images/kernel/20200528-3.jpg)

如上图所示：第二条子通道的建立依然遵守TCP的三次握手，而TCP选项换成了MP_JOIN。
而token是基于key的一个hash值，rand为一个随机数，而HMAC是基于rand的一个hash值。

#### 数据的发送和接收

MPTCP可以选择多条子通道中任意一条来发送数据。MPTCP如果使用传统的TCP的方式
来发送数据，将会出现一部分包在一条子通道，而另一部分包在另外一条子通道。这样的话，防火墙等
中间设备将会收到TCP的序号跳跃的包，因此将会发生丢包等异常情况。为了解决这个问题，MPTCP通过
增加DSN(data sequence number)来管理包的发送，DSN统计总的报文段序号，而每个子通道中的
序号始终是连续。

MPTCP的接收包过程分为两个阶段：第一、每个子通道依据自身序号来重组报文段；第二、MPTCP
的控制模块依据DSN对所有子通道的报文段进行重组。

#### 拥塞控制
MPTCP中拥塞控制的设计需遵守以下两个原则：

第一：MPTCP和传统TCP应该拥有相同的吞吐量，而不是MPTCP中每一条子通道和传统TCP具有相同的吞吐量。

第二：MPTCP在选择子通道的时候应该选择拥塞情况更好的子通道。


#### MPTCP的实现
MPTCP的实现主要分为三部分：

master subsocket

Multi-path control bock(mpcb)

slave subsocket

![](/images/kernel/20200528-4.jpg)

master subsock是一个标准的sock结构体用于TCP通信。mpcb提供开启或关闭子通道、
选择发送数据的子通道以及重组报文段的功能。slave subsocket对应用程序并不可见，他们
都是被mpcb管理并用于发送数据。

