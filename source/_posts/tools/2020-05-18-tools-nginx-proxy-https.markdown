---
layout: post
title: "nginx https代理配置"
date: 2020-05-18 17:23:00 +0800
comments: false
categories:
- 2020
- 2020~05
- tools
- tools~nginx
tags:
---

https://segmentfault.com/a/1190000019563509

---------------

NGINX解决HTTPS代理的方式都属于透传(隧道)模式，即不解密不感知上层流量。具体的方式有如下7层和4层的两类解决方案。

### HTTP CONNECT隧道 (7层解决方案)

客户端给代理服务器发送HTTP CONNECT请求。

代理服务器利用HTTP CONNECT请求中的主机和端口与目的服务器建立TCP连接。

代理服务器给客户端返回HTTP 200响应。

客户端和代理服务器建立起HTTP CONNECT隧道，HTTPS流量到达代理服务器后，直接通过TCP透传给远端目的服务器。代理服务器的角色是透传HTTPS流量，并不需要解密HTTPS。

#### NGINX ngx_http_proxy_connect_module模块

NGINX作为反向代理服务器，官方一直没有支持HTTP CONNECT方法。但是基于NGINX的模块化、可扩展性好的特性，阿里的@chobits提供了ngx_http_proxy_connect_module模块，来支持HTTP CONNECT方法，从而让NGINX可以扩展为正向代理。


### NGINX stream (4层解决方案)

NGINX官方从1.9.0版本开始支持ngx_stream_core_module模块，模块默认不build，需要configure时加上 `--with-stream` 选项来开启。

#### ngx_stream_ssl_preread_module模块

要在不解密的情况下拿到HTTPS流量访问的域名，只有利用TLS/SSL握手的第一个Client Hello报文中的扩展地址SNI (Server Name Indication)来获取。NGINX官方从1.11.5版本开始支持利用ngx_stream_ssl_preread_module模块来获得这个能力，模块主要用于获取Client Hello报文中的SNI和ALPN信息。对于4层正向代理来说，从Client Hello报文中提取SNI的能力是至关重要的，否则NGINX stream的解决方案无法成立。同时这也带来了一个限制，要求所有客户端都需要在TLS/SSL握手中带上SNI字段，否则NGINX stream代理完全没办法知道客户端需要访问的目的域名。

```
	stream {
		resolver 114.114.114.114;
		server {
			listen 443;
			ssl_preread on;
			proxy_connect_timeout 5s;
			proxy_pass $ssl_preread_server_name:$server_port;
		}
	}
```

### test

openssl带servername参数来指定SNI

```
	openssl s_client -connect www.baidu.com:443 -servername www.baidu.com
```

