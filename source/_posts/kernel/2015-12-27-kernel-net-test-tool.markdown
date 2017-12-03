---
layout: post
title: "Web压力测试工具"
date: 2015-12-27 02:51:00 +0800
comments: false
categories:
- 2015
- 2015~12
- kernel
- kernel~net
tags:
---
http://297020555.blog.51cto.com/1396304/592386

```
	*/1 * * * * cd /root/test ; ./curl.sh > /dev/null 2>&1
	*/1 * * * * cd /root/test ; ./ping.sh > /dev/null 2>&1

	curl.sh:
	if ps aux | grep curl | grep "30M" > /dev/null ; then
		echo "..." > /dev/null
	else
		./curl_1.sh &
		./curl_2.sh &
	fi

	curl_1.sh & curl_2.sh:
	URL=192.168.1.3:80/30M
	T=`date "+%F %T"`
	curl $URL -s -o /tmp/df -w "$T %{time_connect} %{time_starttransfer} %{time_total} %{speed_download} %{http_code}\n" >> t_down

	ping.sh:
	date "+%F %T" >> ping_out
	ping -q -f -c 1000 192.168.1.3 >> ping_out
```

res.sh:
```
	#sed -i -e ':a;N;$!ba;s/\(:..\)\n/\1 /g' t_down
	cat ping_out | grep -E "\-|loss" | grep -v statistics > ping_tmp
	sed -i -e ':a;N;$!ba;s/\(:..\)\n/\1 /g' ping_tmp

	python res.py > res_out
	python res2.py
```

统计结果和ping汇聚 res.py:
```
	import time

	ff = open('t1_down').readlines()
	qq = open('t2_down').readlines()
	ping = open('ping_tmp').readlines()

	j = 0
	for i in range(0, len(ff)):
		f = ff[i].strip().split(' ')
		q = qq[i].strip().split(' ')

		if (len(f) != len(q)):
			break

		timeArray = time.strptime(f[0]+" "+f[1], "%Y-%m-%d %H:%M:%S")
		ft = time.mktime(timeArray)

		if (i < len(ff) - 1):
			fn = ff[i+1].strip().split(' ')
			fnt = time.mktime(time.strptime(fn[0] + " " + fn[1], "%Y-%m-%d %H:%M:%S"))
		else:
			fnt = time.mktime(time.strptime("2020-01-01 00:00:00", "%Y-%m-%d %H:%M:%S"))

		po = "0"
		while (j < len(ping)):
			p = ping[j].strip().split(' ')
			pt = time.mktime(time.strptime(p[0]+" "+p[1], "%Y-%m-%d %H:%M:%S"))
			if (pt > fnt):
				break
			j = j + 1
			if (pt >= ft - 10):
				po = p[8]
				break

		print f[0], f[1], int(float(f[6])/1000), int(float(q[6])/1000), po
```

分时段统计res2.py:
```
	#coding:utf-8

	r = open('res_out').readlines()

	hs1 = {}
	hs2 = {}
	hsw = {}
	hl = {}
	hr = {}
	hm = {}

	for line in r:
		arr = line.strip().split(' ')
		h1 = int(arr[1][0:2])
		if h1 >= 2 and h1 <= 9:
			h2 = "H2-9"
		else:
			h2 = "H10-25"
		h3 = 'Hall'
		for h in (h1, h2, h3):
			if h not in hsw:
				hs1[h] = hs2[h] = hsw[h] = hl[h] = hr[h] = hm[h] = 0
			hs1[h] += int(arr[2])
			hs2[h] += int(arr[3])
			hsw[h] += 1
			hl[h] += int(arr[4][0:-1])
			hr[h] += int(arr[5])
			hm[h] += int(arr[6])

	print "时段", "下载次数", "ff(KB/s)", "qq(KB/s)", "提升比例", "丢包率", "recovery_skb", "mark_skb"
	for h in sorted(hs1.keys()):
		s1 = hs1[h]/hsw[h]
		s2 = hs2[h]/hsw[h]
		s3 = hl[h]/hsw[h]
		s4 = hr[h]/hsw[h]
		s5 = hm[h]/hsw[h]
		#print h, hsw[h], s1, s2, 100*(s1-s2)/s2, s3, s4, s5
		print "%s\t%d\t%d\t%d\t%d\t%d\t%d\t%d" % (h, hsw[h], s1, s2, 100*(s1-s2)/s2, s3, s4, s5)
```

#### 一、http_load
http_load以并行复用的方式运行，用以测试web服务器的吞吐量与负载。但是它不同于大多数压力测试工具，它可以以一个单一的进程运行，一般不会把客户机搞死。还可以测试HTTPS类的网站请求。

下载地址：http://www.acme.com/software/http_load/

```
	./http_load -verbose -proxy 192.168.99.6:80 -parallel 24 -seconds 1000 url.txt
```

#### 二、webbench

webbench是Linux下的一个网站压力测试工具，最多可以模拟3万个并发连接去测试网站的负载能力。
```
	用法：webbench -c 并发数 -t 运行测试时间 URL
	如：webbench -c 5000 -t 120 http://www.163.com
```

#### 三、ab
ab是apache自带的一款功能强大的测试工具。安装了apache一般就自带了，用法可以查看它的说明

参数众多，一般我们用到的是-n 和-c

例如：
```
	./ab -c 1000 -n 100 http://www.vpser.net/index.php
```
这个表示同时处理1000个请求并运行100次index.php文件.

#### 四、Siege
一款开源的压力测试工具，可以根据配置对一个WEB站点进行多用户的并发访问，记录每个用户所有请求过程的相应时间，并在一定数量的并发访问下重复进行。
官方：http://www.joedog.org/

使用
```
	siege -c 200 -r 10 -f example.url
```

-c是并发量，-r是重复次数。 url文件就是一个文本，每行都是一个url，它会从里面随机访问的。

