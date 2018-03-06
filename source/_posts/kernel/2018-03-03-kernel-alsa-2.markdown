---
layout: post
title: "Linux ALSA 系统架构"
date: 2018-03-03 18:22:00 +0800
comments: false
categories:
- 2018
- 2018~03
- kernel
- kernel~sound
tags:
---

https://www.linuxidc.com/Linux/2012-07/65903.htm

ALSA是Linux声卡驱动的架构，下面基于linux-2.6.32描述下ALSA系统架构。ALSA系统可以分为alsa-lib、alsa-driver，而alsa-driver又分为core层和底层硬件层。作为开发者，我们只需移植底层硬件层，根据自己硬件特性，实现底层的移植。而core层基本属于ALSA标准框架，不需要自己实现。介绍ALSA框架，下面是大体框架图。

![](/images/kernel/2018-03-03-21.png)

如上图所示，alsa驱动最终会被上层应用调用，这是通过alsa-lib实现的，alsa-lib为alsa-driver封装了许多API，通过这些API，上层应用可以调用到驱动层。而alsa-lib中的这些API，我们可暂时把他们当做一个黑盒子，里面具体实现不需要去关注，下面以linux-2.6.32中ALSA调用函数关系说明其架构。先分析ALSA驱动的注册过程。

#### 一、ALSA驱动的注册

![](/images/kernel/2018-03-03-21.png)

注册流程查看上图，具体的注册过程不在此赘述。

二、打开流程

在ALSA驱动注册完毕以后，当应用程序开始调用时，会有一个过程：打开设备、映射、设置硬件参数、准备工作、触发数据流。下图为整个流程。

![](/images/kernel/2018-03-03-22.png)

带sep0611的是需要自己实现的底层驱动。

三、写数据流程

![](/images/kernel/2018-03-03-23.png)

图中应用程序通过ALSA-lib的API函数写入数据，ALSA-lib调用等待函数等待底层可写。ALSA-lib通过poll系统调用进入底层驱动并将poll信号加入sleep队列阻塞进程。硬件的中断信号触发底层驱动注册的中断处理函数，中断处理函数进而调用ALSA-driver中的函数判读是否该写。ALSA-driver中的函数再调用底层芯片硬件驱动获取硬件当前数据大小。ALSA-driver再判断空闲数据区的大小，如果满足条件就唤醒sleep队列，poll信号从而被唤醒，进而返回给ALSA-lib，ALSA-lib收到信号后再执行往buffer里写数据。
ALSA-lib通过mmap机制将硬件申请的内存映射到用户空间，从而应用程序只需调用ALSA-lib往相应位置写数据，硬件就可以直接读取了。如果映射内存里已有数据，通过DMA传输给codec，codec便开始读取数据并进行解码播放声音了。


http://blog.chinaunix.net/uid-20564848-id-74726.html

https://my.oschina.net/abcijkxyz/blog/788796
