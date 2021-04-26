---
layout: post
title: "Python & tushare 实现命令行盯盘"
date: 2021-04-15 15:22:00 +0800
comments: false
categories:
- 2021
- 2021~04
- tools
- tools~base
tags:
---

https://blog.csdn.net/u011323949/article/details/102937856

#### 依赖
```
	pip install tushare
	pip install pandas
```

#### code
```
	# -*- coding:utf-8 -*-
	import tushare as ts
	import os
	import threading
	import time
	from datetime import datetime

	codes = ['000756', '601288', '601988', '601319', '600929']

	while 1:
		try:
			df = ts.get_realtime_quotes(codes);
		except:
			print "get err\n";
			time.sleep(3);
			continue;

		os.system("clear")
		print datetime.now()
		for k in range(0, len(codes)):
			p1 = float(df['price'][k])
			p2 = float(df['pre_close'][k])
			print "%s %s %.3f %.3f %.3f%%" % (df['code'][k], df['name'][k], p1, p2, (p1 - p2) / p2 * 100)

		time.sleep(10);
```

