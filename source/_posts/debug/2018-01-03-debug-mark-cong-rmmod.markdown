---
layout: post
title: "拥塞控制模块无法卸载"
date: 2018-01-03 00:04:00 +0800
comments: false
published: false
categories:
- 2018
- 2018~01
- debug
- debug~mark
tags:
---

如果：自定义拥塞控制中维护了hash表，且在init时加入hash表，在release时删除或卸载清理hash表。

那么：有些情况会导致分配了拥塞控制模块给sk，但却没调init，导致卸载清理时就找不到模块被哪个sk引用，也就无法清理。

### 可能原因：

#### 1. 拥塞控制初始化时
标准内核：socket()时分配拥塞控制，但在连接建立时才会调 icsk->icsk_ca_ops->init(sk)
如果只调了 socket(), bind(), listen() 那么就不会调 icsk->icsk_ca_ops->init(sk)（所以listen的sk就一定不调init）。

内核可行修改方案：socket -> tcp_init_sock -> tcp_assign_congestion_control 时先分配一个内核内部的拥塞控制，连接建立时再分配实际拥塞控制，然后初始化。
这么改还是有缺陷：先调 socket()，再调 setsockopt 设置拥塞控制模块，因为此时sk->sk_state = TCP_CLOSE，所以不会调init

#### 2. 拥塞控制切换时
拥塞控制切换时：如果是TCP_CLOSE状态也不会调 icsk->icsk_ca_ops->init(sk) 初始化

### 可行解决方法
模仿 /proc/net/tcp 遍历hash表，修改拥塞控制

### 特殊情况：
调 socket 时 sk 不加入hash表，在 listen 或 connect 时才会加入到hash表，如果只调socket、bind然后不再使用该fd，那么sk不仅没调icsk->icsk_ca_ops->init(sk)，也没加入hash表。

此时 /proc/net/tcp 也找不到sk，只能遍历所有进程的所有fd，找出sk再修改

[conglist.tar.gz](/download/debug/conglist.tar.gz)

