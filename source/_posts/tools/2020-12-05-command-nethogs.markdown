---
layout: post
title: "nethogs 监控Linux的每个进程流量"
date: 2020-12-05 22:23:00 +0800
comments: false
categories:
- 2020
- 2020~12
- tools
- tools~command
tags:
---

```
	sudo apt-get install libncurses5-dev
	sudo apt-get install libpcap0.8-dev

	yum install ncurses libpcap
	yum install ncurses-devel libpcap-devel
```

[adjust & fix bug nethogs-0.8.1-kk.tar](/download/tools/nethogs-0.8.1-kk.tar)

orig: https://packages.ubuntu.com/source/xenial/nethogs

```
	Nethogs命令介绍

	nethogs -help
	usage: nethogs [-V] [-h] [-b] [-d seconds] [-v mode] [-c count] [-t] [-p] [-s] [device [device [device ...]]]
	-V : prints version.
	-h : prints this help.
	-b : bughunt mode - implies tracemode.
	-d : delay for update refresh rate in seconds. default is 1.
	-v : view mode (0 = KB/s, 1 = total KB, 2 = total B, 3 = total MB). default is 0.
	-c : number of updates. default is 0 (unlimited).
	-t : tracemode.
	-p : sniff in promiscious mode (not recommended).
	-s : sort output by sent column.
	-a : monitor all devices, even loopback/stopped ones.
	device : device(s) to monitor. default is all interfaces up and running excluding loopback

	When nethogs is running, press:
	q: quit
	s: sort by SENT traffic
	r: sort by RECEIVE traffic
	m: switch between total (KB, B, MB) and KB/s mode


	参数介绍：
	    -V : 显示版本信息，注意是大写字母V.
	    -v：切换显示单位，默认是默认是KB/s（0表示 KB/s，1表示KB，2表示B，3表示MB）
	    -c：检测次数（后面直接跟数字）
	    -a：检测所有的设备
	    -d : 延迟更新刷新速率，以秒为单位。默认值为 1.
	    -t : 跟踪模式.
	    -b : bug 狩猎模式 — — 意味着跟踪模式.
	    -p : 混合模式（不推荐）.
	    device: 要监视的设备名称. 默认为 eth0

	以下是NetHogs的一些交互命令(键盘快捷键)
	    m : 修改单位
	    r : 按流量排序
	    s : 按发送流量排序
	    q : 退出命令提示符
```

