---
layout: post
title: "shell 获取脚本的绝对路径"
date: 2021-06-08 22:10:00 +0800
comments: false
categories:
- 2021
- 2021~06
- tools
- tools~command
tags:
---

https://www.cnblogs.com/FlyFive/p/3640267.html

#### 常见的一种误区，是使用 pwd 命令
该命令的作用是“print name of current/working directory”，这才是此命令的真实含义，当前的工作目录，这里没有任何意思说明，这个目录就是脚本存放的目录。所以，这是不对的。你可以试试 bash shell/a.sh，a.sh 内容是 pwd，你会发现，显示的是执行命令的路径 /home/june，并不是 a.sh 所在路径：/home/june/shell/a.sh 

#### 另一个误人子弟的答案，是 $0

这个也是不对的，这个$0是Bash环境下的特殊变量，其真实含义是：

 Expands to the name of the shell or shell script. This is set at shell initialization.  If bash is invoked with a file of commands, $0 is set to the name of that file. If bash is started with the -c option, then $0 is set to the first argument after the string to be executed, if one is present. Otherwise, it is set to the file name used to invoke bash, as given by argument zero.

 这个$0有可能是好几种值，跟调用的方式有关系：

 使用一个文件调用bash，那$0的值，是那个文件的名字(没说是绝对路径噢)

 使用-c选项启动bash的话，真正执行的命令会从一个字符串中读取，字符串后面如果还有别的参数的话，使用从$0开始的特殊变量引用(跟路径无关了)

 除此以外，$0会被设置成调用bash的那个文件的名字(没说是绝对路径)


#### 正确：

```
	basepath=$(cd `dirname $0`; pwd)
```

dirname $0，取得当前执行的脚本文件的父目录

cd `dirname $0`，进入这个目录(切换当前工作目录)

pwd，显示当前工作目录(cd执行后的)
