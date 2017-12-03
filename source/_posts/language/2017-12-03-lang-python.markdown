---
layout: post
title: "python"
date: 2017-12-03 13:24:00 +0800
comments: false
categories:
- 2017
- 2017~12
- language
- language~python
tags:
---

#### python dict 是指针
```
	[root@localhost tmp]# cat a.py
	a = b = {}
	a[1] = 2
	print a
	print b

	[root@localhost tmp]# python a.py
	{1: 2}
	{1: 2}
```

#### utf8
```
	#coding:utf-8
```

#### 时间戳、int互转
```
	t = time.mktime(time.strptime("2020-01-01 00:00:00", "%Y-%m-%d %H:%M:%S"))
	ts = time.strftime("%Y-%m-%d %H:%M:%S", time.localtime(t))
```

