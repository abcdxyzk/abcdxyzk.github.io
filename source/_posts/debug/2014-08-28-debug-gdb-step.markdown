---
layout: post
title: "gdb 没有debug信息step单步调试"
date: 2014-08-28 16:21:00 +0800
comments: false
categories:
- 2014
- 2014~08
- debug
- debug~gdb
tags:
- gdb
---

#### 一、如果有函数调用，他会进入该函数
```
	step <count>
```
单步跟踪，如果有函数调用，他会进入该函数。进入函数的前提是，此函数被编译有 debug信息。很像 VC等工具中的 step in。后面可以加 count也可以不加，不加表示一条条地执行，加表示执行后面的 count条指令，然后再停住。


#### 二、如果有函数调用，他不会进入该函数
```
	next <count>
```
同样单步跟踪，如果有函数调用，他不会进入该函数。很像 VC等工具中的 step over。后面可以加 count也可以不加，不加表示一条条地执行，加表示执行后面的 count条指令，然后再停住。

#### 三、无debuginfo时需要set step-mode [on/off]
```
	set step-mode [on/off]
	set step-mode on
	#打开 step-mode模式，于是，在进行单步跟踪时，程序不会因为没有 debug信息而不停住。这个参数有很利于查看机器码。

	set step-mode off
	关闭 step-mode模式。
```

#### 四、跟踪子进程
使用gdb调试的时候，gdb只能跟踪一个进程。

可以在fork函数调用之前，通过指令设置gdb调试工具跟踪父进程或子进程。

默认情况下gdb是跟踪父进程的。

```
	set follow-fork-mode child   # 命令设置gdb在fork之后跟踪子进程。
	set follow-fork-mode parent  # 设置跟踪父进程。
```

#### 五、修改值

```
	set $rax=0	# 寄存器

	set m=6		# 变量，需要debuginfo

	p $rsp+0x714	# 内存
	set *0x7f962c1fa564 = 0x6
```

