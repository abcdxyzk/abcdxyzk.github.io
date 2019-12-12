---
layout: post
title: "crash struct"
date: 2019-12-12 11:21:00 +0800
comments: false
categories:
- 2019
- 2019~12
- debug
- debug~kdump、crash
tags:
---

http://bbs.chinaunix.net/thread-4101359-1-1.html

#### 查看结构体中成员的偏移
```
	crash> struct -o task_struct

	crash> struct task_struct
```
