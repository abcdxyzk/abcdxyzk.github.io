---
layout: post
title: "ubuntu dns覆盖写入127.0.0.53"
date: 2020-10-12 20:11:00 +0800
comments: false
categories:
- 2020
- 2020~10
- system
- system~ubuntu
tags:
---

https://blog.csdn.net/evanxuhe/article/details/90229597

/etc/resolve.conf 一直是 nameserver 127.0.0.53，无法修改，因为 ubuntu17.0之后特有，systemd-resolvd服务会一直覆盖

#### 解决办法

```
	sudo systemctl stop systemd-resolved
	sudo systemctl disable systemd-resolved
	sudo apt install unbound
	sudo rm -rf /etc/resolv.conf
	sudo vim  /etc/NetworkManager/NetworkManager.conf
	在 [main] 下面添加
	dns=unbound
	将dns服务替换为unbound
	reboot 开机查看resolve.conf发现nameserver自动配置
```
