---
layout: post
title: "ubuntu 编译内核、模块"
date: 2019-08-22 11:33:00 +0800
comments: false
categories:
- 2019
- 2019~08
- system
- system~ubuntu
tags:
---

https://launchpad.net/ubuntu/+source/linux/+changelog

#### 4.15.0

https://launchpad.net/ubuntu/bionic/+source/linux/+changelog

https://packages.ubuntu.com/xenial/linux-source-4.15.0  
http://security.ubuntu.com/ubuntu/pool/main/l/linux-hwe/linux-source-4.15.0_4.15.0-58.64~16.04.1_all.deb

https://launchpad.net/ubuntu/xenial/amd64/linux-image-unsigned-4.15.0-58-generic-dbgsym  
http://launchpadlibrarian.net/436393485/linux-image-unsigned-4.15.0-58-generic-dbgsym_4.15.0-58.64~16.04.1_amd64.ddeb

https://launchpad.net/ubuntu/bionic/amd64/linux-image-unsigned-4.15.0-58-generic-dbgsym  
http://launchpadlibrarian.net/436226708/linux-image-unsigned-4.15.0-58-generic-dbgsym_4.15.0-58.64_amd64.ddeb

#### 4.18.0

https://launchpad.net/ubuntu/xenial/+source/linux/+changelog

https://packages.ubuntu.com/bionic/linux-source-4.18.0  
http://security.ubuntu.com/ubuntu/pool/main/l/linux-hwe/linux-source-4.18.0_4.18.0-25.26~18.04.1_all.deb

https://launchpad.net/ubuntu/bionic/amd64/linux-image-4.18.0-25-generic-dbgsym  
http://launchpadlibrarian.net/430863032/linux-image-4.18.0-25-generic-dbgsym_4.18.0-25.26~18.04.1_amd64.ddeb

#### gcc 7 编译内核模块时无法找到 stdarg.h 的问题

https://blog.gloriousdays.pw/2018/09/09/cannot-find-stdarg-h-on-linux-kernel-4-15-with-gcc-7-3/

这是一个非常奇怪的错误，出现在 Ubuntu 18.04 上，默认安装的内核版本是 4.15，gcc 是 7.3，在编译内核模块时报错：
```
	In file included from ./include/linux/list.h:9:0,
	                 from ./include/linux/module.h:9,
	                 from /root/Software/newbbr/tcp_tsunami.c:59:
	./include/linux/kernel.h:6:10: fatal error: stdarg.h: No such file or directory
	 #include <stdarg.h>
	          ^~~~~~~~~~
	compilation terminated.
```

gcc 认为找不到 stdarg.h。看这个错误的位置，个人认为应该不是我配置的问题或者是我代码的问题，搜索了一下，也有很多在 4.15 内核上出现的同样错误。目前没有什么很好的解决方案，暂时性的方案是在编译的 Makefile 里面加一行：
```
	ccflags-y=-I/usr/lib/gcc/x86_64-linux-gnu/7/include
```
如果是 gcc 8，就相应把版本改成 8 就可以了

#### 编译内核

```
	sudo apt-get install libncurses-dev flex bison openssl-dev libssl-dev dkms libelf-dev

	make bindeb-pkg -j8
```

按这里没成功 https://wiki.ubuntu.com/Kernel/BuildYourOwnKernel

#### 编译perf

```
	cd tools/perf
	make
```
