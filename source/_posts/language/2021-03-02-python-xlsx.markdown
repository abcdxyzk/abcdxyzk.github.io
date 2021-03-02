---
layout: post
title: "python读取xlsx"
date: 2021-03-02 23:07:00 +0800
comments: false
categories:
- 2021
- 2021~03
- language
- language~python
tags:
---

```
	pip install xlrd==1.2.0
```

https://www.cnblogs.com/tynam/p/11204895.html

```
	#coding:utf-8
	import xlrd
	import sys

	def readxls(filename):
		data = xlrd.open_workbook(filename)
		table = data.sheet_by_index(0)
		datas = []

		for rn in range(table.nrows):
			datas.append(table.row_values(rn))

		return datas
	f1='mm.xlsx'
	datas = readxls(f1)
```
