---
layout: post
title: "Ubuntu+SS"
date: 2021-06-06 22:17:00 +0800
comments: false
categories:
- 2021
- 2021~06
- tools
- tools~socksvpn
tags:
- vpn
---

#### Shadows启动报错undefined symbol EVP_CIPHER_CTX_cleanup

vim /usr/local/lib/python2.7/dist-packages/shadowsocks/crypto/openssl.py

cleanup替换为reset

#### 测试socks5命令：

curl --socks5 192.168.8.107:8388 http://www.baidu.com/

-------------------

https://www.codetd.com/article/1418936

#### 1 安装
```
	sudo apt-get install python-pip
	pip install shadowsocks
```

#### 2 配置

vim config.json
```
	{
		"server":"0.0.0.0",
		"port_password": {
			"8388": "your_password1",
			"8389": "your_password2"
		},
		"timeout":600,
		"method":"aes-256-cfb",
		"fast_open": false
	}
```

#### 3. 启动
```
	ssserver -c config.json start

	ssserver -c config.json -d start
	ssserver -c config.json -d stop
```

#### 4. server
vim /etc/systemd/system/shadowsocks.service

```
	[Unit]
	Description=Shadowsocks
	After=network.target

	[Service]
	Type=forking
	PIDFile=/run/shadowsocks/server.pid
	PermissionsStartOnly=true
	ExecStartPre=/bin/mkdir -p /run/shadowsocks
	ExecStartPre=/bin/chown root:root /run/shadowsocks
	ExecStart=/usr/local/bin/ssserver --pid-file /var/run/shadowsocks/server.pid -c /etc/shadowsocks/config.json -d start
	Restart=on-abort
	User=root
	Group=root
	UMask=0027

	[Install]
	WantedBy=multi-user.target
```

设置文件权限：
```
	chmod 755 /etc/systemd/system/shadowsocks.service
```

启动服务：
```
	systemctl start shadowsocks
	systemctl enable shadowsocks
```

#### 5. 客户端

https://github.com/shadowsocks/shadowsocks-qt5/releases

