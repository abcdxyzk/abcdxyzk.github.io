---
layout: post
title: "ipt_CLUSTERIP"
date: 2021-06-15 21:12:00 +0800
comments: false
categories:
- 2021
- 2021~06
- kernel
- kernel~ipsec
tags:
---

Linux内核自带了一种多机高可用性的解决方法。在每台机器上配置一个相同ip、mac的多播地址。可以给CLUSTERIP设置多个node，经过这个地址的数据流会按一定算法分配到某个node上。只有持有某个node的机器会收、发属于这个node的数据流，其他机器会drop数据包。

说明：

1) 同一个CLUSTERIP的同一个node可以配置在多台机器上，这样每台都会处理包，但这种情形不是想要的。

2) node 可以只有一个，这种情况下流经CLUSTERIP的数据流都属于这个node

3) 添加CLUSTERIP命令:

```
	iptables -I INPUT -d 100.64.1.2 -i eth2 -p icmp -j CLUSTERIP --new --clustermac 01:00:5E:44:55:66 --total-nodes 2 --local-node 1 --hashmode sourceip
```

4) 切换node的方法:
```
	echo +1 > /proc/net/ipt_CLUSTERIP/xx.xx.xx.xx
	echo -2 > /proc/net/ipt_CLUSTERIP/xx.xx.xx.xx
```

---------------

http://www.linux-ha.org/ClusterIP

http://www.rkeene.org/projects/info/wiki/102

https://www.douban.com/note/389412837/

modprobe ipt_CLUSTERIP

这样就会在/proc/net下创建个ipt_CLUSTERIP目录。

使用iptables创建一条规则，这条规则同时也会创建一个clusterip_config，和规则是一一对应的，同样在

/proc/net/ipt_CLUSTERIP下也会创建一个文件，文件名是虚拟服务器的ip (vip)。在iptables里指定。

#### 在node1上执行
iptables -I INPUT -d 192.168.122.33(这个就个vip) -i eth0 (哪个接口属于集群) -p icmp -j CLUSTERIP --new(必须有的) --hashmode sourceip(用source ip做hash，还有其他的？) --clustermac 01:00:5E:7F:18:0A(虚拟服务器的mac地址，必须为多播地址) --total-nodes 2(这个集群里一共有几台机器) --local-node 1(本地机器编号，只有编号为1的机器才有权力与客户端通信)。这个命令会将为eth0设备添加这个多播地址，见dev_mc_add。

#### 添加完命令后，给这个Node加个ip
ifconfig eth0:0 192.168.122.33， 这样node的内核才回相应arp请求。
所以，客户端在想访问192.168.122.33前，会发送一个arp请求，node1收到后，看到这个ip是自己的，于是便回复你找的就是我，把自己的mac地址发送给客户端。当这个arp回复经过OUTPUT链的arp_mangle钩子时，这个函数会判断 这个arp回复里的消息，如果发现客户端想知道vip的mac地址，就把这个mac地址修改为clustermac，也就是01:00:5E:7F:18:0A。

这样以后客户端发的数据包就会变成广播包了， 所有的node ip都是192.168.122.33，所以所有的node都会收到这个消息，但是只有node1才有权力回复消息。其他node会在clusterip target里把数据包丢掉。

CLUSTERIP TARGET还有一个作用，就是把数据包pky_type修改为PACKET_HOST,让上层乐意收包。

