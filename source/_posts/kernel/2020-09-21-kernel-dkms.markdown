---
layout: post
title: "DKMS简介"
date: 2020-09-21 11:54:00 +0800
comments: false
categories:
- 2020
- 2020~09
- kernel
- kernel~base
tags:
---

https://www.cnblogs.com/wwang/archive/2011/06/21/2085571.html

DKMS全称是Dynamic Kernel Module Support，在内核版本变动之后可以自动重新生成新的模块。

#### 安装

```
	sudo apt-get install dkms
```

#### 流程

![](/images/kernel/20200921.jpg)

DKMS主要的命令分别是add、build、install、uninstall和remove，另外，还可以执行"dkms status"查看目前DKMS系统维护的模块的状态。

DKMS要求我们的代码目录必须以" <module>-<module-version>"的格式命名。

#### 命令

以hello-0.1为例，代码copy到"/usr/src/hello-0.1"

```
	# 添加
	sudo dkms add -m hello -v 0.1

	# 编译
	sudo dkms build -m hello -v 0.1
	生成模块路径： /var/lib/dkms/hello/0.1/*/*/module/

	# 安装
	sudo dkms install -m hello -v 0.1

	# 移除
	sudo dkms uninstall -m hello -v 0.1

	# 彻底删除，会把/var/lib/dkms下彻底删除
	sudo dkms remove -m hello -v 0.1 --all

	# 以上的每个步骤查看执行后的状态
	dkms status
```

#### 目录结构

```
	/usr/src/hello-0.1/
	├── dkms.conf
	├── hello.c
	└── Makefile
```
在Makefile中要使用变量$(KVERSION)指定内核版本号，这样我们在执行dkms时，就可以用“-k”选项来设定为哪个内核版本编译模块。

#### dkms.conf

```
	PACKAGE_NAME="hello"
	PACKAGE_VERSION="0.1"
	CLEAN="make clean"
	MAKE[0]="make all KVERSION=$kernelver"
	BUILT_MODULE_NAME[0]="hello"
	DEST_MODULE_LOCATION[0]="/updates"
	AUTOINSTALL="yes"
```

PACKAGE_NAME和PACKAGE_VERSION和文件夹的命名是一致的。

CLEAN的命令是每次build的时候第一条执行的动作。

MAKE[0]用来设定编译的命令，一般情况下是不用设定的。在本例中，就可以把MAKE[0]这行删掉。但在下面这种情况下就需要设定了。比如，您的Makefile里有多个target，分别为all、debug、release等，不指定MAKE[0]时，编译会选择第一个target来执行，也就是make all，如果您想执行make release来编译，就需要在dkms.conf里明确设定。

BUILD_MODULE_NAME[0]用来指定模块的名称，一般情况下也可以不设定。

DEST_MODULE_LOCATION[0]用来设定模块安装的目的地址，本例是"/lib/module/$(KVERSION)/updates"。

AUTOINSTALL="yes"表示在Linux引导之后DKMS会自动对这个模块执行Build和Install的动作，当然如果模块已经处于该状态的话，相应的动作是不用再执行的。

### 基于DKMS制作驱动程序的DEB安装包

制作DEB包依赖于dh-make，请首先执行 sudo apt-get install dh-make 安装。

在模块处于"Built State"的条件下，执行 sudo dkms mkdeb -m hello -v 0.1 可以在目录“/var/lib/dkms/hello/0.1/deb”下生成deb包。

DKMS还提供了mktarball和mkrpm来制作tarball和RPM安装包。


