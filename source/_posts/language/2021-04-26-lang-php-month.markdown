---
layout: post
title: "PHP 使用 +1month、-1month 的问题"
date: 2021-04-26 20:56:00 +0800
comments: false
categories:
- 2021
- 2021~04
- language
- language~php
tags:
---

https://blog.csdn.net/qq_32737755/article/details/92690573

经常会有人被strtotime结合-1 month, +1 month, next month的时候搞得很困惑, 然后就会觉得这个函数有点不那么靠谱, 动不动就出问题. 用的时候就会很慌…

比如 2018-07-31 这天执行代码:

```
	date("Y-m-d",strtotime("-1 month"))
```
怎么输出是的2018-07-01? 上个月的最后一天不应该是2018-06-30吗？


我们来模拟下date内部的对于这种事情的处理逻辑:
```
	1. 先做-1 month, 那么当前是07-31, 减去一以后就是06-31.
	2. 再做日期规范化, 因为6月没有31号, 所以就好像2点60等于3点一样, 6月31就等于了7月1
```

### 怎么办

#### 只用每月1号处理

#### 从PHP5.3开始呢, date新增了一系列修正短语, 来明确这个问题, 那就是”first day of” 和 “last day of”, 也就是你可以限定好不要让date自动”规范化”:

```
    var_dump(date("Y-m-d", strtotime("last day of -1 month", strtotime("2017-03-31"))));
    //输出2017-02-28
    var_dump(date("Y-m-d", strtotime("first day of +1 month", strtotime("2017-08-31"))));
    输出2017-09-01
```

