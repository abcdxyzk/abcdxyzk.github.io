---
layout: post
title: "火狐Android版跳转APP的问题"
date: 2021-04-26 20:33:00 +0800
comments: false
categories:
- 2021
- 2021~04
- android
- android~base
tags:
---

http://mozilla.com.cn/thread-422133-1-1.html

火狐浏览器访问，天涯，京东，淘宝，中关村等网站频繁跳转唤醒优酷，淘宝，京东，天猫等APP

暂时的解决方法是
```
	about:config

	network.protocol-handler.external-default  false
```

同时会导致微信支付等无法唤起

