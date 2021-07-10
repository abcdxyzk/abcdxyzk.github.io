---
layout: post
title: "html中制表符(TAB)的转义字符"
date: 2021-07-10 11:52:00 +0800
comments: false
categories:
- 2021
- 2021~07
- language
- language~web
tags:
---

HTML特殊字符不包括TAB. TAB应该也可以用`&#9;`表示. 但只有在`<PRE>...</PRE>`这样的标记内部才起作用. 其他地方只相当于一个空格. 这和`&nbsp;`不一样
