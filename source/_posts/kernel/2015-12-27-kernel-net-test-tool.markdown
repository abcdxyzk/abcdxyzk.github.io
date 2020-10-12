---
layout: post
title: "Web压力测试工具"
date: 2015-12-27 02:51:00 +0800
comments: false
categories:
- 2015
- 2015~12
- kernel
- kernel~tcp
tags:
---

#### curl wget 不验证证书进行https请求

 wget 'https://x.x.x.x/get_ips' --no-check-certificate

curl 'https://x.x.x.x/get_ips' -k

----------

https://blog.csdn.net/hqzxsc2006/article/details/50547684

#### 通过curl得到http各阶段的响应时间

url_effective The URL that was fetched last. This is most meaningful if you've told curl to follow location: headers.

filename_effective The ultimate filename that curl writes out to. This is only meaningful if curl is told to write to a file with the --remote-name or --output option. It's most useful in combination with the --remote-header-name option. (Added in 7.25.1)

http_code http状态码，如200成功,301转向,404未找到,500服务器错误等。(The numerical response code that was found in the last retrieved HTTP(S) or FTP(s) transfer. In 7.18.2 the alias response_code was added to show the same info.)

http_connect The numerical code that was found in the last response (from a proxy) to a curl CONNECT request. (Added in 7.12.4)

time_total 总时间，按秒计。精确到小数点后三位。 （The total time, in seconds, that the full operation lasted. The time will be displayed with millisecond resolution.）

time_namelookup DNS解析时间,从请求开始到DNS解析完毕所用时间。(The time, in seconds, it took from the start until the name resolving was completed.)

time_connect 连接时间,从开始到建立TCP连接完成所用时间,包括前边DNS解析时间，如果需要单纯的得到连接时间，用这个time_connect时间减去前边time_namelookup时间。以下同理，不再赘述。(The time, in seconds, it took from the start until the TCP connect to the remote host (or proxy) was completed.)

time_appconnect 连接建立完成时间，如SSL/SSH等建立连接或者完成三次握手时间。(The time, in seconds, it took from the start until the SSL/SSH/etc connect/handshake to the remote host was completed. (Added in 7.19.0))

time_pretransfer 从开始到准备传输的时间。(The time, in seconds, it took from the start until the file transfer was just about to begin. This includes all pre-transfer commands and negotiations that are specific to the particular protocol(s) involved.)

time_redirect 重定向时间，包括到最后一次传输前的几次重定向的DNS解析，连接，预传输，传输时间。(The time, in seconds, it took for all redirection steps include name lookup, connect, pretransfer and transfer before the final transaction was started. time_redirect shows the complete execution time for multiple redirections. (Added in 7.12.3))

time_starttransfer 开始传输时间。在client发出请求之后，Web 服务器返回数据的第一个字节所用的时间(The time, in seconds, it took from the start until the first byte was just about to be transferred. This includes time_pretransfer and also the time the server needed to calculate the result.)

size_download 下载大小。(The total amount of bytes that were downloaded.)

size_upload 上传大小。(The total amount of bytes that were uploaded.)

size_header  下载的header的大小(The total amount of bytes of the downloaded headers.)

size_request 请求的大小。(The total amount of bytes that were sent in the HTTP request.)

speed_download 下载速度，单位-字节每秒。(The average download speed that curl measured for the complete download. Bytes per second.)

speed_upload 上传速度,单位-字节每秒。(The average upload speed that curl measured for the complete upload. Bytes per second.)

content_type 就是content-Type，不用多说了，这是一个访问我博客首页返回的结果示例(text/html; charset=UTF-8)；(The Content-Type of the requested document, if there was any.)

num_connects Number of new connects made in the recent transfer. (Added in 7.12.3)

num_redirects Number of redirects that were followed in the request. (Added in 7.12.3)

redirect_url When a HTTP request was made without -L to follow redirects, this variable will show the actual URL a redirect would take you to. (Added in 7.18.2)

ftp_entry_path The initial path libcurl ended up in when logging on to the remote FTP server. (Added in 7.15.4)

ssl_verify_result ssl认证结果，返回0表示认证成功。( The result of the SSL peer certificate verification that was requested. 0 means the verification was successful. (Added in 7.19.0))

1、可以直接访问使用：

```
	curl -o /dev/null -s -w %{http_code}:%{http_connect}:%{content_type}:%{time_namelookup}:%{time_redirect}:%{time_pretransfer}:%{time_connect}:%{time_starttransfer}:%{time_total}:%{speed_download} www.baidu.com
```

输出变量需要按照%{variable_name}的格式，如果需要输出%，double一下即可，即%%，同时，\n是换行，\r是回车，\t是TAB。 

-w 指定格式化文件

-o 请求重定向到,不带此参数则控制台输出返回结果

-s 静默，不显示进度

2、也可以定义时间格式化文件访问

```
	#vim  curl-time.txt 
	\n
	    	      http: %{http_code}\n
	               dns: %{time_namelookup}s\n
	          redirect: %{time_redirect}s\n
	      time_connect: %{time_connect}s\n
	   time_appconnect: %{time_appconnect}s\n
	  time_pretransfer: %{time_pretransfer}s\n
	time_starttransfer: %{time_starttransfer}s\n
	     size_download: %{size_download}bytes\n
	    speed_download: %{speed_download}B/s\n
	                  ----------\n
	        time_total: %{time_total}s\n
	\n
```

```
	curl -w "@curl_time.txt"  -s  -H "Content-Type: application/json" --insecure --header 'Host: passport.500.com' --data '{"platform":"android","userimei":"F5D815EA2BD8DBARD","app_channel":"10000","mbimei":"9DB358AF","version":"3.1.4","username":"hqzx","userpass":"976af4"}' --compressed https://119.147.113.177/user/login
```

----------

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

[http_load 改进版下载 http_load-09Mar2016-kk.tar.gz](/download/tools/http_load-09Mar2016-kk.tar.gz)  
改进点：  
2018-01-19:  
1. 异常时打印更多信息("want_bytes=%ld got_bytes=%ld sport=%d connect_at=%ld now=%ld last=%ld")  
2. http1.0 改成 http1.1 支持多次request  
3. 增加 [ -requests times ] 参数, 在一条流中会发起times次request, 默认为1  
2018-01-26:  
4. 增加 [ -fastopen ] 参数，http协议增加fastopen测试，fastopen连接时改为阻塞模式。非阻塞模式syn无法附带数据  
2018-04-12:  
5. 修复 num_connections 可能出现的统计错误，以及fastopen可能出现的请求超时  
2018-06-13:  
6. https增加SNI信息，Makefile默认开启https支持  
2019-02-19:  
7. 修复IPV6的bug  
2020-08-29:
8. fastopen 支持非阻塞模式-nonblock，但第一次连接还得用block


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

