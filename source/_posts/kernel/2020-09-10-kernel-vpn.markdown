---
layout: post
title: "vpn"
date: 2020-09-10 04:39:00 +0800
comments: false
categories:
- 2020
- 2020~09
- kernel
- kernel~net
tags:
---

### 方案一

client ---------------> hongkong ----------------> server  
           xor/drop      squid


### 方案二

client ---------------> guangzhou -------------------> hongkong ----------------> server  
          xor/drop       squid      xor/drop/icmp+mss    squid



[squid](/blog/cats/tools~squid/)

[icmp](/blog/2020/07/09/kernel-tcp-icmp/)

[mss](/blog/2020/07/09/kernel-adjust-mss/)

[xor](/blog/2020/09/10/kernel-digest-xor/)

drop: 丢弃异常reset、fin等包
