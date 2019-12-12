---
layout: post
title: "gdb struct"
date: 2019-12-12 11:23:00 +0800
comments: false
categories:
- 2019
- 2019~12
- debug
- debug~gdb
tags:
- gdb
---

http://bbs.chinaunix.net/thread-4101359-1-1.html

#### 查看结构体中成员的偏移
```
	(gdb) p &((struct task_struct *)0)->prio

	(gdb) ptype struct malloc_chunk
```
