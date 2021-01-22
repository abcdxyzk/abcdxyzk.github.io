---
layout: post
title: "php获取月初和月尾时间"
date: 2021-01-22 21:30:00 +0800
comments: false
categories:
- 2021
- 2021~01
- language
- language~php
tags:
---

#### 获取当前时间的月初月尾
```
	echo date("Y-m-01", time());
	echo PHP_EOL;
	echo date('Y-m-t', time());

	// 输出
	2017-09-01
	2017-09-30
```

#### 获取上一月的月初月尾
```
	echo date("Y-m-01", strtotime('-1 month'));
	echo PHP_EOL;
	echo date('Y-m-t', strtotime('-1 month'));

	// 输出
	2017-08-01
	2017-08-31
```


#### 获取下一月的月初月尾
```
	echo date("Y-m-01", strtotime('+1 month'));
	echo PHP_EOL;
	echo date('Y-m-t', strtotime('+1 month'));

	// 输出
	2017-10-01
	2017-10-31
```

