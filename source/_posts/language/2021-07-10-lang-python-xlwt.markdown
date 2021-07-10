---
layout: post
title: "python读写Excel，优化PHPExcel"
date: 2021-07-10 09:32:00 +0800
comments: false
categories:
- 2021
- 2021~07
- language
- language~python
tags:
---

PHPExcel getCalculatedValue() 可能很慢，一次需要30ms。预先有python处理后就很快了

```
	yum install python-pip
	pip install xlrd==1.2.0 xlwt
```

---------

https://www.cnblogs.com/caesar-id/p/11802440.html#top

```
	#coding:utf-8
	import xlrd
	import xlwt
	import sys
	
	if len(sys.argv) != 3:
		print 'python export.py xlsx sheet'
		exit(1)
	
	filename = sys.argv[1]
	sheetname = sys.argv[2]
	
	data = xlrd.open_workbook(filename)
	#table = data.sheet_by_index(0)
	table = data.sheet_by_name(sheetname.decode('utf-8'))
	
	workbook = xlwt.Workbook(encoding = 'utf-8')
	worksheet = workbook.add_sheet(sheetname)
	
	for rn in range(table.nrows):
		line = table.row_values(rn)
		j = 0
		for v in line:
			worksheet.write(rn, j, line[j])
			j = j + 1
	
	workbook.save((filename+'.xls'))
```

