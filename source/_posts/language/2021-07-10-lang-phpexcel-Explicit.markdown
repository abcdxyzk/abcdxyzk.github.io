---
layout: post
title: "PHPExcel防止大数以科学计数法显示"
date: 2021-07-10 10:31:00 +0800
comments: false
categories:
- 2021
- 2021~07
- language
- language~php
tags:
---

在使用PHPExcel来进行数据导出时，常常需要防止有些数字(如手机号、身份证号)以科学计数法显示，我们可以采用下面的方式来解决:

setCellValueExplicit第三个参数用PHPExcel_Cell_DataType::TYPE_STRING

```
	setCellValueExplicit('A1', 13211223344, PHPExcel_Cell_DataType::TYPE_STRING);
```

