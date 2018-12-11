---
layout: post
title: "setterm 防止黑屏"
date: 2018-12-12 01:17:00 +0800
comments: false
categories:
- 2018
- 2018~12
- tools
- tools~command
tags:
---
https://unix.stackexchange.com/questions/8056/disable-screen-blanking-on-text-console

#### 防止黑屏

```
	setterm -blank 0 -powersave off

	# cat /sys/module/kernel/parameters/consoleblank
```


-----------------

#### 名字
setterm - 设置终端属性

#### 概要
setterm [选项]

#### 描述
setterm向终端写一个字符串到标准输出，调用终端的特定功能。在虚拟终端上使用，将会改变虚拟终端的输出特性。不支持的选项将被忽略。

#### 选项
对于布尔选项（on或off），默认设置为on。

```
   简洁8色如下：黑色，红色，绿色，黄色，蓝色，洋红色，青色，或白色
   black, red, green, yellow, blue, magenta, cyan, or white.
   16色是8色加上灰度或明暗，在红色、绿色、黄色、蓝色、洋红色、青色或白色之后加上灰度或明暗
   red, green, yellow, blue, magenta, cyan, or white + grey 或 bright
   各种颜色选项可以独立设置，其中设置多个模式的结果（例如，下划线和-半明亮）是硬件相关的。
   -term 终端名字
        覆盖环境变量TERM.
   -reset 显示终端重置字符串，它通常将终端重新设置为电源的状态？？(测试未见任何效果)
   -initialize 清空屏幕。
   -cursor [on|off] 显示或关闭光标（测试时，没有效果）
   -repeat [on|off] 只在虚拟主机上有效：键盘打开或关闭（测试时，显示不支持）
   -appcursorkeys [on|off] 只在虚拟主机上有效
        将光标键应用程序模式设置为on或off. 
   -linewrap [on|off] (virtual consoles only)
        自动换行或关闭。
   -default：将终端的呈现选项设置为默认值。
   -foreground 8-color|default 设置前景文本颜色
   -background 8-color|default 设置背景文本颜色。
   -ulcolor 16-color (virtual consoles only)为加下划线的字符设置颜色。
   -hbcolor 16-color (virtual consoles only)设置半明字符的颜色。
   -inversescreen [on|off] (virtual consoles only)颠倒的屏幕颜色。前台和后台交换，下划线和半亮交换。
   -bold [on|off] 打开或关闭粗体（额外亮度）模式
   -half-bright [on|off]将昏暗（半亮度）模式开启或关闭
   -blink [on|off]开启或关闭闪烁模式
   -reverse [on|off]打开或关闭反向视频模式，字符和字符背景交换颜色（-inversescreen是全屏交换）
   -underline [on|off]在开启或关闭状态下显示下划线模式
   -store 存储终端当前的呈现选项
   -clear all：同命令clear
   -clear rest：测试时报参数错误
   -tabs [tab1 tab2 tab3 ...] 不带参数，测试结果如下。带参数没效果。
            root@myzr:~# setterm -tabs
            10        20        30        40        50        60        70
            12345678901234567890123456789012345678901234567890123456789012345678901234567890
            T      T       T       T       T       T       T       T       T       T      T
   -clrtabs [tab1 tab2 tab3 ...] 测试时报终端不支持：setterm: terminal xterm does not support --clrtabs
   -regtabs [1-160] 测试时报终端不支持：setterm: terminal xterm does not support --regtabs
   -blank [0-60|force|poke] 设置不活动的时间间隔，在几分钟内，之后屏幕将自动变白（如果可用的话，使用APM）
        force：即使按键被按下，也要保持屏幕空白。
        poke：开启屏幕
   -dump [1-NR_CONS]  将给定虚拟控制台（带有属性）的快照写入-file选项中指定的文件，覆盖该文件，默认文件是screen.dump
   -append [1-NR_CONS] 类似-dump，但是将其附加到快照文件，而不是重写它。
   -file dump文件名
   -msg [on|off] 启用或禁用发送内核printk（）消息到控制台。
   -msglevel 1-8 设置内核打印等级。
   -powersave on|vsync 将监视器放入VESA vsync挂起模式。测试无效
   -powersave hsync 将监视器放入VESA hsync挂起模式。测试无效
   -powersave powerdown 将监视器放入VESA关闭模式。测试无效
   -powersave [off]节能模式。测试无效
   -powerdown [0-60]测试无效
   -blength [0-2000]：以毫秒为间隔设置钟的持续时间，没有参数，默认是0。测试时不支持
   -bfreq [freqnumber] 将钟频率设置为赫兹，没有参数，默认是0。测试时不支持
   -version 输出版本信息
   -help  输出帮助信息
```
