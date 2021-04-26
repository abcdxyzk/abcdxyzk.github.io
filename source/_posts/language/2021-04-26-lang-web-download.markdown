---
layout: post
title: "html实现点击直接下载文件"
date: 2021-04-26 21:06:00 +0800
comments: false
categories:
- 2021
- 2021~04
- language
- language~web
tags:
---

http://www.divcss5.com/html5/h57401.shtml


这样当用户打开浏览器点击链接的时候就会直接下载文件。

但是有个情况，比如txt,png,jpg等这些浏览器支持直接打开的文件是不会执行下载任务的，而是会直接打开文件，这个时候就需要给a标签添加一个属性“download”;

#### 使用 a 标签
```
	<a href="/user/test/xxxx.txt" download="文件名.txt">点击下载</a>
```

