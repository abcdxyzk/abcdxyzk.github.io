---
layout: post
title: "httpd log配置"
date: 2021-06-08 14:40:00 +0800
comments: false
categories:
- 2021
- 2021~06
- tools
- tools~apache2
tags:
---

时间格式

```
	%{ %Y-%m-%d %H:%M:%S %s}t
```

vim /etc/httpd/conf/httpd.conf

```
	LogFormat "\"%h\" \"%l\" \"%u\" \"%{ %Y-%m-%d\" \"%H:%M:%S\" \"%s\"}t \"%P\" \"%p\" \"%H\" \"%m\" \"%U\" \"%q\" \"%>s\" \"%b\" \"%{Referer}i\" \"%{User-Agent}i\" \"%{SSL_PROTOCOL}x\" \"%{SSL_CIPHER}x\"" combined
	LogFormat "\"%h\" \"%l\" \"%u\" \"%{ %Y-%m-%d\" \"%H:%M:%S\" \"%s\"}t \"%P\" \"%p\" \"%H\" \"%m\" \"%U\" \"%q\" \"%>s\" \"%b\" \"%{Referer}i\" \"%{User-Agent}i\" \"%{SSL_PROTOCOL}x\" \"%{SSL_CIPHER}x\"" common

	LogFormat "\"%h\" \"%l\" \"%u\" \"%{ %Y-%m-%d\" \"%H:%M:%S\" \"%s\"}t \"%P\" \"%p\" \"%H\" \"%m\" \"%U\" \"%q\" \"%>s\" \"%b\" \"%{Referer}i\" \"%{User-Agent}i\" \"%{SSL_PROTOCOL}x\" \"%{SSL_CIPHER}x\" \"%I\" \"%O\"" combinedio
```

vim /etc/httpd/conf.d/ssl.conf

```
	TransferLog logs/ssl_access_log
	改为
	CustomLog logs/ssl_access_log \
			"\"%h\" \"%l\" \"%u\" \"%{ %Y-%m-%d\" \"%H:%M:%S\" \"%s\"}t \"%P\" \"%p\" \"%H\" \"%m\" \"%U\" \"%q\" \"%>s\" \"%b\" \"%{Referer}i\" \"%{User-Agent}i\" \"%{SSL_PROTOCOL}x\" \"%{SSL_CIPHER}x\""

	CustomLog logs/ssl_request_log \
		"%t %h %{SSL_PROTOCOL}x %{SSL_CIPHER}x \"%r\" %b"
	改为
	CustomLog logs/ssl_request_log \
		"\"%h\" \"%l\" \"%u\" \"%{ %Y-%m-%d\" \"%H:%M:%S\" \"%s\"}t \"%P\" \"%p\" \"%H\" \"%m\" \"%U\" \"%q\" \"%>s\" \"%b\" \"%{Referer}i\" \"%{User-Agent}i\" \"%{SSL_PROTOCOL}x\" \"%{SSL_CIPHER}x\""
```


处理

```
	cat /var/log/httpd/access_log | awk -F'^"|" "|"$' '{ if (NF==20) { for(i=2;i<NF;i++) printf $i"   "; print ""; } }'
	cat /var/log/httpd/ssl_access_log | awk -F'^"|" "|"$' '{ if (NF==20) { for(i=2;i<NF;i++) printf $i"   "; print ""; } }'
```

--------------

http://blog.sina.com.cn/s/blog_1512521570102wbua.html



定制日志文件的格式涉及到两个指令，即LogFormat指令和CustomLog指令，默认httpd.conf文件提供了关于这两个指令的几个 示例。

LogFormat指令定义格式并为格式指定一个名字，以后我们就可以直接引用这个名字。CustomLog指令设置日志文件，并指明日志文件所用 的格式（通常通过格式的名字）。

LogFormat指令的功能是定义日志格式并为它指定一个名字。例如，在默认的httpd.conf文件中，我们可以找到下面这行代码：

```
	LogFormat "%h %l %u %t \"%r\" %>s %b" common
```

该指令创建了一种名为"common"的日志格式，日志的格式在双引号包围的内容中指定。格式字符串中的每一个变量代表着一项特定的信息，这些信息按照格 式串规定的次序写入到日志文件。

Apache文档已经给出了所有可用于格式串的变量及其含义，下面是其译文：

```
	%…a: 远程IP地址
	%…A: 本地IP地址
	%…B: 已发送的字节数，不包含HTTP头
	%…b: CLF格式的已发送字节数量，不包含HTTP头。例如当没有发送数据时，写入‘-’而不是0。
	%…{FOOBAR}e: 环境变量FOOBAR的内容
	%…f: 文件名字
	%…h: 远程主机
	%…H 请求的协议
	%…{Foobar}i: Foobar的内容，发送给服务器的请求的标头行。
	%…l: 远程登录名字（来自identd，如提供的话）
	%…m 请求的方法
	%…{Foobar}n: 来自另外一个模块的注解"Foobar"的内容
	%…{Foobar}o: Foobar的内容，应答的标头行
	%…p: 服务器响应请求时使用的端口
	%…P: 响应请求的子进程ID。
	%…q 查询字符串（如果存在查询字符串，则包含"?"后面的部分；否则，它是一个空字符串。）
	%…r: 请求的第一行
	%…s: 状态。对于进行内部重定向的请求，这是指*原来*请求 的状态。如果用%…>s，则是指后来的请求。
	%…t: 以公共日志时间格式表示的时间（或称为标准英文格式）
	%…{format}t: 以指定格式format表示的时间
	%…T: 为响应请求而耗费的时间，以秒计
	%…u: 远程用户（来自auth；如果返回状态（%s）是401则可能是伪造的）
	%…v: 响应请求的服务器的ServerName
	%…V: 依照UseCanonicalName设置得到的服务器名字
```

在所有上面列出的变量中，"…"表示一个可选的条件。如果没有指定条件，则变量的值将以"-"取代。分析前面来自默认httpd.conf文件的 LogFormat指令示例，可以看出它创建了一种名为"common"的日志格式，其中包括：远程主机，远程登录名字，远程用户，请求时间，请求的第一 行代码，请求状态，以及发送的字节数。

有时候我们只想在日志中记录某些特定的、已定义的信息，这时就要用到"…"。如果在"%"和变量之间放入了一个或者多个HTTP状态代码，则只有当 请 求返回的状态代码属于指定的状态代码之一时，变量所代表的内容才会被记录。例如，如果我们想要记录的是网站的所有无效链接，那么可以使用：

```
	LogFormat @4{Referer}i BrokenLinks
```

反之，如果我们想要记录那些状态代码不等于指定值的请求，只需加入一个"!"符号即可：

```
	LogFormat %!200U SomethingWrong
```

