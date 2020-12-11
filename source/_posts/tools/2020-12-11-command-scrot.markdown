---
layout: post
title: "Linux命令行截屏"
date: 2020-12-11 12:33:00 +0800
comments: false
categories:
- 2020
- 2020~12
- tools
- tools~command
tags:
---

#### ubuntu
```
	sudo apt-get install scrot
```

#### centos

scrot0.8 https://github.com/dreamer/scrot

[scrot0.8](/download/tools/scrot0.8.tar)

http://packages.psychotic.ninja/7/base/x86_64/RPMS/giblib-1.2.4-22.el7.psychotic.x86_64.rpm

http://packages.psychotic.ninja/7/base/x86_64/RPMS/giblib-devel-1.2.4-22.el7.psychotic.x86_64.rpm

[giblib-1.2.4-22.el7.psychotic.x86_64.rpm](/download/tools/giblib-1.2.4-22.el7.psychotic.x86_64.rpm)

[giblib-devel-1.2.4-22.el7.psychotic.x86_64.rpm](/download/tools/giblib-devel-1.2.4-22.el7.psychotic.x86_64.rpm)

```
	yum install imlib2
	yum install imlib2-devel

	rpm -ivh giblib-1.2.4-22.el7.psychotic.x86_64.rpm
	rpm -ivh giblib-devel-1.2.4-22.el7.psychotic.x86_64.rpm

	$ ./configure
	$ make
	# make install
```


scrot https://github.com/resurrecting-open-source-projects/scrot

[scrot](/download/tools/scrot.tar)

```
	yum install automake
	yum install autoconf
	yum install autoconf-archive.noarch 
	yum install libXtst-devel
	yum install libXcomposite-devel

	$ ./autogen.sh
	$ ./configure
	$ make
	# make install
```

#### 用Scrot截屏

1.截取整个桌面十分简单。只需运行Scrot命令，不需要任何参数，然后它会保存一张整个桌面的截屏，以(日期标记).png的文件形式保存在当前文件目录下。

```
	$ scrot
```

你也可以指定保存目标文件夹和截图文件名。

```
	$ scrot ~/Pictures/my_desktop.png
```

2.截取特定窗口或矩形区域

Scrot允许你在桌面选择一个特定的窗口或定义一个矩形区域来截图。使用下列命令来实现：

```
	$ scrot -s
```

运行这个命令后，继续用你的鼠标单击任意窗口或画出一个矩形，它能够触发对选定窗口/区域的屏幕截取。（LCTT译注，还要使用鼠标，伐快乐）

有时候你选定的区域或窗口可能会被桌面的其它窗口部分遮挡。在这种情况下，你在截屏前需要一点时间来清理那个部分。那正是延迟截屏能够帮到你的，就像下面所描述的那样。

3.延迟截屏

延迟截取在各种情况下都很实用。就在截图前，你可能想要移动一下窗口，激活一下菜单，或是触发特定时间（如通知）等等。 使用“-d N”参数，我们可以将截屏进程延迟N秒。

```
	$ scrot -s -d 5
```

4.调整截屏质量

你可以在1到100的范围内调整截取的图像质量（数字越大质量越高）。默认质量设置为75。

```
	$ scrot -q 50
```

5.调整截屏尺寸

你可以在1到100的范围内调整截取的图像尺寸（数字越大尺寸越大）。减小截屏的尺寸到原图的10％：

```
	$ scrot -t 10
```

6.将截取的截屏传递给其它命令

Scrot允许你发送保存的截屏图像给任意一个命令作为它们的输入。这个选项在你想对截屏图像做任意后期处理的时候十分实用。截屏的文件名/路径跟随于“$f”字符串之后。

```
	$ scrot -e 'mv $f ~/screenshots'
```

