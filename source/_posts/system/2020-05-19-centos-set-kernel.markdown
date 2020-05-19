---
layout: post
title: "centos 设置默认启动内核"
date: 2020-05-19 11:21:00 +0800
comments: false
categories:
- 2020
- 2020~05
- system
- system~centos
tags:
---

https://www.4spaces.org/centos7-change-kernel-order/

#### 查看当前默认启动内核
```
	grub2-editenv list
```


#### 查看所有内核
```
	cat /boot/grub2/grub.cfg | grep menuentry
	menuentry 'CentOS Linux (3.10.0-693.5.2.el7.x86_64) 7 (Core)' --class centos --class gnu-linux --class gnu --class os --unrestricted $menuentry_id_option 'gnulinux-3.10.0-693.el7.x86_64-advanced-a5c5c2e2-9baf-46e5-9703-ee9d6b421f66' {
```

#### 设置新的启动内核
```
	grub2-set-default 'CentOS Linux (3.10.0-693.5.2.el7.x86_64) 7 (Core)'
```
