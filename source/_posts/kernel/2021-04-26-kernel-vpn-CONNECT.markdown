---
layout: post
title: "vpn CONNECT方法"
date: 2021-04-26 20:46:00 +0800
comments: false
categories:
- 2021
- 2021~04
- kernel
- kernel~vpn
tags:
- vpn
---

https://www.cnblogs.com/xyl1932432873/p/7735866.html

CONNECT这个方法的作用就是把服务器作为跳板，让服务器代替用户去访问其它网页，之后把数据原原本本的返回给用户。这样用户就可以访问到一些只有服务器上才能访问到的网站了，这就是HTTP代理。

假如我想通过代理访问这个博客（www.web-tinker.com），我就需要建立一个TCP连接，连接到服务器监听的那个端口，然后给服务器发送一个HTTP头。下面就是这个HTTP头的内容：
```
	CONNECT www.web-tinker.com:80 HTTP/1.1
	Host: www.web-tinker.com:80
	Proxy-Connection: Keep-Alive
	Proxy-Authorization: Basic *
	Content-Length: 0
```
所有的HTTP头都是类似的，第一行是方法名、主要参数、HTTP版本。接着一行一个参数，最后用两个换行来结束。这个地方应该填写验证的用户名和密码。


发送完这个请求之后，就是服务器端响应请求了。如果用户名和密码验证通过，就会返回一个状态码为200的响应信息。虽然状态码是200，但是这个状态描述不是OK，而是Connection Established。
```
	HTTP/1.1 200 Connection Established
```

如果用户名和密码验证不通过。会返回一个407的状态码，状态表述是Unauthorized。表示没有权限访问代理服务器。
```
	HTTP/1.1 407 Unauthorized
```

验证通过之后，我们就可以做普通的HTTP操作了。完全可以把现在的代理服务器看作是请求连接的Internet服务器，也就是说可以像直接访问普通的服务器一样，使用GET、POST等方法来请求Internet服务器上的页面了。我们在发送CONNECT请求的时候就已经告诉了服务器我们需要访问的Internet服务器，上面我用了这个博客的网址。现在我们要访问这个博客的主页就可以发送一个简单的GET请求。
```
	GET / HTTP/1.1
	Host: www.web-tinker.com
	Content-Length: 0
```

