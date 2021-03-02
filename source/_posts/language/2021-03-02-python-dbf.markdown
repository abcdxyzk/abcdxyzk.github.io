---
layout: post
title: "python读写dbf数据库"
date: 2021-03-02 23:08:00 +0800
comments: false
categories:
- 2021
- 2021~03
- language
- language~python
tags:
---

```
	pip install dbfread
	pip install dbfpy
```

https://www.cnblogs.com/zhugaopeng/p/9745800.html

```
	#coding:utf-8
	import dbfread
	from dbfpy import dbf
	import itertools
	import xlrd
	import sys

	def writeBmh(datas, filename, header):
		db = dbf.Dbf(filename, new=True)
		for kv in header:
			# field = field.encode('GBK')
			db.addField(kv)

		idx = dict()
		n = 0
		for field in datas[0]:
			idx[field] = n
			n  = n + 1

		for record in datas:
			if record[0] == 'bmh':
				continue

			rec = db.newRecord()
			for kv in header:
				value = record[idx[kv[0]]]
				#value = value.encode('utf-8')
				#print value, type(value)
				#if type(value) == unicode:
				if kv[1] == 'D':
					value = value.replace('-', '')
				elif kv[1] == 'C':
					rec[kv[0]] = value.encode('GBK')
				elif kv[1] == 'N':
					rec[kv[0]] = int(value)
				else:
					rec[kv[0]] = value
			rec.store()
		db.close()


	def readxls(filename):
		data = xlrd.open_workbook(filename)
		table = data.sheet_by_index(0)
		datas = []

		for rn in range(table.nrows):
			datas.append(table.row_values(rn))

		return datas

	f1='mm.xlsx'
	datas = readxls(f1)
	h=[['bmh','C',10],['bmm','C',76],['oldbmh','C',10],['bm','C',30],['bz','C',50]]
	o='m.dbf'
	writeBmh(datas,o,h)
```
