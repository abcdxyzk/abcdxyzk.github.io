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

