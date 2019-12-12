---
layout: post
title: "ssh反向连接"
date: 2019-12-12 11:04:00 +0800
comments: false
categories:
- 2019
- 2019~12
- tools
- tools~base
tags:
---

https://www.cnblogs.com/eshizhan/archive/2012/07/16/2592902.html

内网主机主动连接到外网主机，又被称作反向连接（Reverse Connection）

### ssh

A主机：外网，ip：123.123.123.123，sshd端口：2221

B主机：内网，sshd端口：2223

#### 1.1.首先在B上执行
```
	ssh -NfR 1234:localhost:2223 user1@123.123.123.123 -p2221
```

这句话的意思是将A主机的1234端口和B主机的2223端口绑定，相当于远程端口映射（Remote Port Forwarding）。

像平时一样连接到A主机的1234端口就可以控制内网B主机了
```
	ssh localhost -p1234
```

### Autossh的用法

```
	autossh -M 5678 -NfR 1234:localhost:2223 user1@123.123.123.123 -p2221
```

比之前的命令添加的一个-M 5678参数，负责通过5678端口监视连接状态，连接有问题时就会自动重连.


