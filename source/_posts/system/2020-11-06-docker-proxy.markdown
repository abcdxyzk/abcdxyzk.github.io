---
layout: post
title: "docker proxy"
date: 2020-11-06 16:59:00 +0800
comments: false
categories:
- 2020
- 2020~11
- system
- system~docker
tags:
---

https://www.jianshu.com/p/b26fad1534d9


该方法适用于Debian/Ubuntu，RedHat/CentOS系统。修改配置后，会持续生效，其中的设置将覆盖docker.service中的选项

#### 创建目录
```
	mkdir -p /etc/systemd/system/docker.service.d
```

#### 添加网络代理配置文件
```
	vim /etc/systemd/system/docker.service.d/http-proxy.conf
```
加入如下配置

proxy-addr为代理IP或域名；proxy-port为代理端口；NO_PROXY后面接不需要代理的仓库的域名或者IP，以英文逗号结尾

```
	[Service]
	Environment="HTTP_PROXY=http://proxy-addr:proxy-port" "HTTPS_PROXY=http://proxy-addr:proxy-port" "NO_PROXY=localhost,127.0.0.1,docker-registry.somecorporation.com"
```

#### 重载配置并重启dockers服务
```
	systemctl daemon-reload
	systemctl restart docker
```
