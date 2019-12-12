---
layout: post
title: "gdb子进程"
date: 2019-12-12 10:23:00 +0800
comments: false
categories:
- 2019
- 2019~12
- debug
- debug~gdb
tags:
- gdb
---

https://blog.csdn.net/fingding/article/details/46459095

#### 1. follow-fork-mode

用法：set follow-fork-mode [parent|child] 

进入gdb后，直接设置，默认是parent

所以如果想要调试子进程，进入gdb后设置set follow-fork-mode child，然后设置子进程的断点

可用使用show follow-fork-mode 来查询当前fork模式

使用follow-fork-mode，只能调试一个进程，不能同时调试父子进程

#### 2. detach-on-fork mode

用法：set detach-on-fork [on|off]

on: 只调试父进程或子进程的其中一个(根据follow-fork-mode来决定)，这是默认的模式。

off: 父子进程都在gdb的控制之下，其中一个进程正常调试(根据follow-fork-mode来决定),另一个进程会被设置为暂停状态。

如果设置了set detach-on-fork off且follow-fork-mode为parent，fork后子进程并不运行，而是处于暂停状态。
