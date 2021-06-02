---
layout: post
title: "WebSocket协议"
date: 2021-06-02 22:24:00 +0800
comments: false
categories:
- 2021
- 2021~06
- tools
- tools~base
tags:
---

https://zhuanlan.zhihu.com/p/145628937

#### 概念

WebSocket 是基于TCP/IP协议，独立于HTTP协议的通信协议。

WebSocket 是双向通讯，有状态，客户端一（多）个与服务端一（多）双向实时响应（客户端 ⇄ 服务端）。

WebSocket 是应用在浏览器的 Socket （是 Socket 模型接口的实现），Socket 是一个网络通信接口 （通信规范）。

WebSocket协议端口是80。

WebSocket SSL协议端口是443。

Socket是TCP/IP协议的网络数据通讯接口（一种底层的通讯的方式）。

Socket是IP地址和端口号的组合。例如：192.168.1.100:8080。

#### 版本

RFC 6455 规范 是大多数浏览器实现的 WebSocket API 协议。

#### 工作原理

1. 用户打开Web浏览器，并访问Web站点。

2. Web浏览器（客户端）与Web服务端建立连接。

3. Web浏览器（客户端）能定时收发Web服务端数据，Web服务端也能定时收发Web浏览器数据。

WebSocket协议不受同源策略影响。

#### 请求消息体

```
	# 请求头部分
	# [请求方式] [资源路径] [版本]
	GET /xxx HTTP/1.1
	# 主机。
	Host: server.example.com
	# 协议升级。
	Upgrade: websocket
	# 连接状态。
	Connection: Upgrade
	# websocket客户端生成的随机字符。
	Sec-WebSocket-Key: dGhlIHNhbXBsZSBub25jZQ==
	# websocket协议的子协议，自定义字符，可以理解为频道。
	Sec-WebSocket-Protocol: chat, superchat
	# websocket协议的版本是13。
	Sec-WebSocket-Version: 13
```

#### 响应消息体

```
	# 响应头部分
	# [版本] [状态码]
	HTTP/1.1 101 Switching Protocols
	# 协议升级。
	Upgrade: websocket
	# 连接状态。
	Connection: Upgrade
	# WebSocket服务端根据Sec-WebSocket-Key生成的随机字符。
	Sec-WebSocket-Accept: s3pPLMBiTxaQ9kYGzzhZRbK+xOo=
	# WebSocket协议的子协议，自定义字符，可以理解为频道。
	Sec-WebSocket-Protocol: chat
```

Upgrade字段仅限HTTP/1.1版本协议，不适合HTTP/2.0版本协议。

101 Switching Protocols 是HTTP协议状态码，不是websocket协议状态码。

#### 状态码

连接成功状态码

101：HTTP协议切换为WebSocket协议。

连接关闭状态码

1000：正常断开连接。

1001：服务器断开连接。

1002：websocket协议错误。

1003：客户端接受了不支持数据格式（只允许接受文本消息，不允许接受二进制数据，是客户端限制不接受二进制数据，而不是websocket协议不支持二进制数据）。

1006：异常关闭。

1007：客户端接受了无效数据格式（文本消息编码不是utf-8）。

1009：传输数据量过大。

1010：客户端终止连接。

1011：服务器终止连接。

1012：服务端正在重新启动。

1013：服务端临时终止。

1014：通过网关或代理请求服务器，服务器无法及时响应。

1015：TLS握手失败。

连接关闭状态码是WebSocket对象的onclose属性返回的。

其他状态码不常用，所以就不列举说明。

------------------

#### server.py
```
	#coding:utf-8
	import socket
	addr = ('192.168.8.107', 88)
	fd = socket.socket()
	fd.bind(addr)
	fd.listen(5)
	while True:
		conn,addr = fd.accept()
		rcv = conn.recv(4096)
		rarr = rcv.split('\r\n\r\n', -1)
		if len(rarr) != 0:
			print(rarr[0])
			#file_data = rarr[1]
			status = "HTTP/1.1 101 Switching Protocols\r\n"
			headers = "Upgrade: websocket\r\nConnection: Upgrade\r\nDate: Sat, 1 Mar 2021 12:33:44 GMT\r\nContent-Type: text/plain\r\nPragma:no-cache\r\n\r\n"
			send_data = status + headers
			conn.send(bytes(send_data))
			while True :
				rcv = conn.recv(100)
				print(rcv)
				conn.send("jjjjjj" + rcv)
		conn.close()
```

#### client.py
```
	#coding:utf-8
	import socket
	import time
	addr = ('192.168.8.107', 88)
	s = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
	try:
		ret = s.connect(addr)
	except socket.error as msg:
		print (msg)
	print (ret)
	strs="GET /test.txt HTTP/1.1\r\nHost:test.com\r\nUpgrade: websocket\r\nConnection: Upgrade\r\n\r\n"
	tt = s.send(strs)
	print (tt)
	while True:
		rstrs="kkkkkkkkkkkkkkkkk"
		s.send(bytes(rstrs))
		time.sleep(1)
		t=s.recv(100)
		print (t)
	s.close()
```
