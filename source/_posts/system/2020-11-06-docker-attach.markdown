---
layout: post
title: "Docker容器进入的4种方式"
date: 2020-11-06 17:03:00 +0800
comments: false
categories:
- 2020
- 2020~11
- system
- system~docker
tags:
---

https://www.cnblogs.com/xhyan/p/6593075.html

创建一个守护态的Docker容器
```
	$ sudo docker run -itd ubuntu:14.04 /bin/bash  
```

进入Docker容器比较常见的几种做法如下：

### 一、使用docker exec进入Docker容器

```
	$ sudo docker ps  
	$ sudo docker exec -it 775c7c9ee1e1 /bin/bash
```

### 二、使用docker attach进入Docker容器

```
	$ sudo docker attach 44fc0f0582d9  
```
  使用该命令有一个问题。当多个窗口同时使用该命令进入该容器时，所有的窗口都会同步显示。如果有一个窗口阻塞了，那么其他窗口也无法再进行操作。

  因为这个原因，所以docker attach命令不太适合于生产环境，平时自己开发应用时可以使用该命令。

### 三、使用SSH进入Docker容器

略

### 四、使用nsenter进入Docker容器

略

