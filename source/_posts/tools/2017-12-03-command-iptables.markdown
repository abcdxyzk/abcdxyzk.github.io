---
layout: post
title: "iptables"
date: 2017-12-03 13:58:00 +0800
comments: false
categories:
- 2017
- 2017~12
- tools
- tools~command
tags:
---

http://blog.csdn.net/l241002209/article/details/43987933

#### 1、添加
添加规则有两个参数：-A和-I。其中  
-A是添加到规则的末尾；  
-I可以插入到指定位置，没有指定位置的话默认插入到规则的首部。


#### 2、查看
```
	iptables -nvL –line-number

	 -L 查看当前表的所有规则，默认查看的是filter表，如果要查看NAT表，可以加上-t NAT参数
	 -n 不对ip地址进行反查，加上这个参数显示速度会快很多
	 -v 输出详细信息，包含通过该规则的数据包数量，总字节数及相应的网络接口
	 –line-number 显示规则的序列号，这个参数在删除或修改规则时会用到
```

#### 3
```
	iptables -nvL
	iptables -F # 清除所有规则，但不改变默认策略
	iptables -P INPUT ACCEPT  # INPUT 默认策略
	iptables -P OUTPUT ACCEPT # OUTPUT 默认策略

	iptables -I INPUT -s 192.168.1.5 -j DROP # 头部插入
	iptables -A INPUT -p tcp --dport 22 -j ACCEPT # 尾部追加，规则按顺序匹配的，匹配到就返回

	iptables -D INPUT -s 192.168.1.5 -j DROP
	iptables -D INPUT 2

```

4、修改
修改使用-R参数
```
	iptables -R INPUT 3 -j ACCEPT
```

----------------------

https://blog.csdn.net/zqixiao_09/article/details/53401321

### NAT地址转换

#### iptables nat 原理
同filter表一样，nat表也有三条缺省的"链"(chains)：

##### PREROUTING:目的DNAT规则

把从外来的访问重定向到其他的机子上，比如内部SERVER，或者DMZ。

因为路由时只检查数据包的目的ip地址，所以必须在路由之前就进行目的PREROUTING DNAT;
系统先PREROUTING DNAT翻译——>再过滤（FORWARD）——>最后路由。
路由和过滤（FORWARD)中match 的目的地址，都是针对被PREROUTING DNAT之后的。

##### POSTROUTING:源SNAT规则

在路由以后在执行该链中的规则。

系统先路由——>再过滤（FORWARD)——>最后才进行POSTROUTING SNAT地址其match 源地址是翻译前的。

OUTPUT:定义对本地产生的数据包的目的NAT规则



#### 内网访问外网  -J SNAT
-j SNAT 源网络地址转换，SNAT就是重写包的源IP地址, SNAT 只能用在nat表的POSTROUTING链里

固定public 地址（外网接口地址）的最基本内访外SNAT
```
	iptables -t nat -A POSTROUTING -s 192.168.0.0/24 -o eth0 -j SNAT --to 你的eth0地址
```

-j MASQUERADE

用于外网口public地址是DHCP动态获取的（如ADSL）
```
	iptables -t nat  -A POSTROUTING –o eth1 –s 192.168.1.0/24 –j MASQUERADE
	iptables -t nat  -A POSTROUTING -o ppp0  -j  MASQUERADE
```

#### 外网访问内网 –J DNT

DNAT：目的网络地址转换，重写包的目的IP地址

##### 典型的DNAT的例子

外部接口ip：210.83.2.206 内部接口ip：192.168.1.1

ftp服务器　：　ip 192.168.1.3 web服务器　：　ip 192.168.1.4
```
	iptables -t nat -A PREROUTING -d 210.83.2.206 -p tcp --dport 21 -j DNAT --to 192.168.1.3
	iptables -t nat -A PREROUTING -d 210.83.2.206 -p tcp --dport 80 -j DNAT --to 192.168.1.4
```

DNAT用于内部SERVER的load-balance（即CISCO的rotery）
```
	iptables –t nat –A PREROUTING –d 219.142.217.161 –j DNAT --to-destination 192.168.1.24-192.168.1.25
```

##### DNAT  带端口映射(改变SERVER的端口)
一个FTP SERVER从内部192.168.100.125:21映射到216.94.87.37:2121的例子
```
	iptables -t nat -A PREROUTING -p tcp -d 216.94.87.37 --dport 2121 -j DNAT --to-destination 192.168.100.125:21
```

