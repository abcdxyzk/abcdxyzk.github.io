---
layout: post
title: "gitignore 排除包含"
date: 2020-05-19 11:11:00 +0800
comments: false
categories:
- 2020
- 2020~05
- tools
- tools~git
tags:
- git
---

想忽略某个文件夹，但又不想忽略这个文件夹下的某个子目录或文件

```
	application/*
	!application/language/
	!application/README
```
