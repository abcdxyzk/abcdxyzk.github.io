---
layout: post
title: "perf 火焰图分析程序性能"
date: 2021-06-06 22:35:00 +0800
comments: false
categories:
- 2021
- 2021~06
- debug
- debug~perf
tags:
---

https://www.cnblogs.com/happyliu/p/6142929.html

#### 使用火焰图展示结果

1、Flame Graph项目位于GitHub上：https://github.com/brendangregg/FlameGraph

2、可以用git将其clone下来：git clone https://github.com/brendangregg/FlameGraph.git


我们以perf为例，看一下flamegraph的使用方法：

#### 1、第一步
```
	sudo perf record -e cpu-clock -g -p 28591
```

Ctrl+c结束执行后，在当前目录下会生成采样数据perf.data.

#### 2、第二步

用perf script工具对perf.data进行解析
```
	perf script -i perf.data &> perf.unfold
```

#### 3、第三步

将perf.unfold中的符号进行折叠：

```
	./stackcollapse-perf.pl perf.unfold &> perf.folded
```

#### 4、最后生成svg图：
```
	./flamegraph.pl perf.folded > perf.svg
```

![](/images/debug/20210606.png)

