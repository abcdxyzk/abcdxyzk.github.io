---
layout: post
title: "MPTCP pre_established fully_established"
date: 2020-07-19 01:37:00 +0800
comments: false
categories:
- 2020
- 2020~07
- kernel
- kernel~mptcp
tags:
---

### pre_established

只在subflow的客户端起作用，在收到synack时置为1，收到第4个ack时置为0，防止在synack到第四个ack期间发送数据包。

因为服务端要用第三个ack初始化一些数据？？？

### fully_established

普通建连成功标志？？？
