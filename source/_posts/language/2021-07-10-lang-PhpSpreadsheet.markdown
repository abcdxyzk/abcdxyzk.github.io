---
layout: post
title: "PhpSpreadsheet的简单使用"
date: 2021-07-10 10:13:00 +0800
comments: false
categories:
- 2021
- 2021~07
- language
- language~php
tags:
---

[composer.tar](/download/tools/composer.tar)


https://www.cnblogs.com/woods1815/p/11372007.html

https://www.e-learn.cn/topic/3761556



由于PHPExcel已经不再维护，PhpSpreadsheet是PHPExcel的下一个版本。PhpSpreadsheet是一个用纯PHP编写的库，并引入了命名空间，PSR规范等。

## 安装
```
	composer require phpoffice/phpspreadsheet
```

GitHub下载： https://github.com/PHPOffice/PhpSpreadsheet


## 读写
```
	require_once 'composer/vendor/autoload.php';

	其他基本和PHPExcel一样
```
