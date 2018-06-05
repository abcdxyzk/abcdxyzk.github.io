---
layout: post
title: "nginx https/nginx 配置"
date: 2018-06-06 00:50:00 +0800
comments: false
categories:
- 2018
- 2018~06
- tools
- tools~nginx
tags:
---

curl wget 不验证证书进行https请求
```
	wget 'https://x.x.x.x/get_ips' --no-check-certificate
	curl 'https://x.x.x.x/get_ips' -k
```

#### 服务端

生成证书和私匙
```
	openssl req -newkey rsa:4096 -nodes -keyout test_private.perm -new -x509 -sha512 -days 3650 -subj "/CN=test.com/" -out test.crt
```
test_private.perm 是私匙, test.crt 是证书

其中CN和nginx.conf中的server_name一样


vim /etc/nginx/nginx.conf
```
	http {
		server {
			listen  443;
			server_name test.com;
			ssl on;
			ssl_certificate /root/test.crt;
			ssl_certificate_key /root/test_private.perm;
			location / {
				root /var/www/html;
				index index.html;
			}
		}
		...
	}
```

#### 客户端

自建证书得不到信任，所以会提示：
curl: (60) Peer's certificate issuer has been marked as not trusted by the user.

解决方法：

拿服务器证书
```
	openssl s_client -showcerts -connect www.baidu.com:443
```

curl 参数带证书
```
	curl -v 'https://test.com/kk' --resolve 'test.com:443:192.168.2.7' --trace-time --cacert /root/test.crt
```

或者将证书加到信任的证书列表中

```
	cat /root/test.crt >> /etc/pki/ca-trust/extracted/pem/tls-ca-bundle.pem
	curl -v 'https://test.com/kk' --resolve 'test.com:443:192.168.2.7' --trace-time
```

访问的host一定要是证书中CN(commonname), 不然会提示：
curl: (51) Unable to communicate securely with peer: requested domain name does not match the server's certificate.




