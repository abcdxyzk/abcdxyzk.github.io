---
layout: post
title: "nginx 四层转发配置"
date: 2018-06-06 02:03:00 +0800
comments: false
categories:
- 2018
- 2018~06
- tools
- tools~nginx
tags:
- nginx
---

nginx rpm: http://nginx.org/packages/mainline/centos/7/x86_64/RPMS/

nginx source: http://nginx.org/en/download.html

nginx stream: http://nginx.org/en/docs/stream/ngx_stream_core_module.html


http://www.52devops.com/chuck/1153.html

#### 一、nginx四层代理

Nginx 1.9.0 开发版发布，该版本增加了 stream module 用于一般的 TCP 代理和负载均衡。

#### 三、nginx四层代理实战
##### 3.1编译安装nginx

编译安装nginx需指定–with-stream参数
```
    [root@linux-node1 nginx-1.9.12]#./configure --prefix=/usr/local/nginx-1.9.12 --user=www --group=www --with-http_ssl_module --with-http_stub_status_module --with-file-aio --with-stream
    [root@linux-node1 nginx-1.9.12]#make && make install
```

##### 3.2 使用nginx实现ssh四层代理

编辑nginx配置文件并启动

```
    [root@linux-node1 conf]# vim nginx.conf
    worker_processes  1;
    events {
        worker_connections  1024;
    }
    stream {  #类似于7层的http段
        upstream ssh_proxy {
            hash $remote_addr consistent;
            server 192.168.56.11:22;
            server 192.168.56.12:22;
        }
        server {
            listen 2222;
            proxy_connect_timeout 1s;
            proxy_timeout 300s;
            proxy_pass ssh_proxy;
        }
    }
    [root@linux-node1 conf]# ../sbin/nginx
    [root@linux-node1 conf]# netstat -lntup|grep nginx
    tcp        0      0 0.0.0.0:2222            0.0.0.0:*               LISTEN      61127/nginx: master
```

连接ssh,从结果看把请求抛向了linux-node2的ssh

```
    [root@linux-node1 conf]# ssh -p 2222 root@192.168.56.11
    root@192.168.56.11's password:
    Last login: Sun Mar  6 17:29:42 2016 from linux-node1.example.com
    [root@linux-node2 ~]# Connection to 192.168.56.11 closed by remote host.
```

