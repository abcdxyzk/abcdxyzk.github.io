---
layout: post
title: "nginx 代理配置"
date: 2020-05-18 16:52:00 +0800
comments: false
categories:
- 2020
- 2020~05
- tools
- tools~nginx
tags:
---

### 正向代理、反向代理 没有区别

客户端 ----> 代理服务器(发起访问请求) ----> 网站  
客户端 <---- 代理服务器(响应的内容)   <---- 网站  

### 配置：(https 代理配置较麻烦)

#### ngx_http_proxy_module 代理配置：

```
	server {
		listen 88;
		# resolver 8.8.8.8
		location / {
			proxy_pass $http_host$http_request_uri;
			# proxy_pass http://192.168.56.101:88; 多级代理的中间设备用这条

			# proxy_connect_timeout 600;
			# ...
		}
	}
```

#### ngx_stream_proxy_module 代理配置：

NGINX官方从1.9.0版本开始支持ngx_stream_core_module模块，模块默认不build，需要configure时加上 `--with-stream` 选项来开启。

配置见 [nginx https代理配置](/blog/2020/05/18/tools-nginx-proxy-https/)
