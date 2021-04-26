---
layout: post
title: "PHP session有效期session.gc_maxlifetime"
date: 2021-04-26 21:01:00 +0800
comments: false
categories:
- 2021
- 2021~04
- language
- language~php
tags:
---

https://www.jb51.net/article/26890.htm

PHP中的session有效期默认是1440秒（24分钟）【weiweiok 注：php5里默认的是180分】，也就是说，客户端超过24分钟没有刷新，当前session就会失效。很明显，这是不能满足需要的。

服务器的操作，只是需要进行如下的步骤：

1、把“session.use_cookies”设置为1，打开Cookie储存SessionID，不过默认就是1，一般不用修改；

2、把“session.cookie_lifetime”改为正无穷（当然没有正无穷的参数，不过999999999和正无穷也没有什么区别）;

3、把“session.gc_maxlifetime”设置为和“session.cookie_lifetime”一样的时间；

在PHP的文档中明确指出，设定session有效期的参数是session.gc_maxlifetime。可以在php.ini文件中，或者通过ini_set()函数来修改这一参数。
