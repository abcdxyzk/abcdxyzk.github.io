---
layout: post
title: "git修改commit"
date: 2021-06-01 14:12:00 +0800
comments: false
categories:
- 2021
- 2021~06
- tools
- tools~git
tags:
---

http://blog.chinaunix.net/uid-15007890-id-3220876.html


## 一、修改最后一次 commit
```
	git commit --amend
```

## 二、修改再之前 commit
#### 1. 查看之前commit
```
	git rebase -i master~5 //最后五次
```
#### 2. 显示结果如下，把准备修改的commit前面的 pick 改为 edit ，并 :wq 保存退出
```
	pick 92b4951 2009-08-08: ×××××××
	pick 92b4952 2009-08-07: ×××××××
	pick 92b4953 2009-08-06: ×××××××
	pick 92b4954 2009-08-05: ×××××××
	pick 92b4955 2009-08-04: ×××××××

	# Rebase 9ef2b1f..92b495b onto 9ef2b1f
	#
	# Commands:
	#  pick = use commit
	#  edit = use commit, but stop for amending //改上面的 pick 为 edit
	#  squash = use commit, but meld into previous commit
	#
	# If you remove a line here THAT COMMIT WILL BE LOST.
	# However, if you remove everything, the rebase will be aborted.
	#
```

#### 3. 进行修改
```
	git commit --amend	# 进行修改，完成后 :wq 退出
	git rebase --continue	# 完成操作
```

