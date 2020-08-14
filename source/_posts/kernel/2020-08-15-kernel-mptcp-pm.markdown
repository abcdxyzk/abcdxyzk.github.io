---
layout: post
title: "MPTCP pm"
date: 2020-08-15 00:58:00 +0800
comments: false
categories:
- 2020
- 2020~08
- kernel
- kernel~mptcp
tags:
- mptcp
---


https://www.cnblogs.com/fenglt/p/8570343.html

http://www.doc88.com/p-2354903996876.html

#### fullmesh

两边IP都会建连，即建立 n*m 条连接

IP查看 cat /proc/net/mptcp_fullmesh


#### ndiffports 两边只在一组IP上建立多条连接。
```
	echo ndiffports > /proc/sys/net/mptcp/mptcp_path_manager
	echo 5 > /sys/module/mptcp_ndiffports/parameters/num_subflows # 总共建5条，即额外再建4条
```

#### netlink

通过genetlink和应用层交互，在应用层实现pm功能。

#### binder
将松散源路由（Loose Source and Record Routing, LSRR）选项添加到MPTCP，以确保MPTCP子流使用所有可用网关，即采用LSRR分发子流的数据包。Binder路径管理算法允许用户终端设备从网关直接获得更高的网络吞吐量，而不需要进行任何修改，可用图a和b谁明其工作原理。理论上，只有在网络中设置了LSRR选项，才能使其B>0，从而使得Binder路径管理算法发挥良好效果。

![](/images/kernel/20200815.png)


松散源路由：允许相邻两个IP地址之间跳过多个网络。

通俗解释：发送端指明了流量或者数据包必须经过的IP地址清单，但如果需要，也可以经过一些其他的地址。换句话说，不用考虑数据包经过的确切地址，只要它经过这些地址就可以。


