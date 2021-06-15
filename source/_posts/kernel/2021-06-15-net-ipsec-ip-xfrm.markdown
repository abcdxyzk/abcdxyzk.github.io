---
layout: post
title: "IP XFRM配置示例：手动配置IPSec"
date: 2021-06-15 22:17:00 +0800
comments: false
categories:
- 2021
- 2021~06
- kernel
- kernel~ipsec
tags:
---

https://blog.csdn.net/sahusoft/article/details/8827362

### 1、拓扑
```
	192.168.18.101 <=======> 192.168.18.102
```

### 2、配置192.168.18.101

```
	ip xfrm state add src 192.168.18.101 dst 192.168.18.102 proto esp spi 0x00000301 mode tunnel auth md5 0x96358c90783bbfa3d7b196ceabe0536b enc des3_ede 0xf6ddb555acfd9d77b03ea3843f2653255afe8eb5573965df
	ip xfrm state add src 192.168.18.102 dst 192.168.18.101 proto esp spi 0x00000302 mode tunnel auth md5 0x99358c90783bbfa3d7b196ceabe0536b enc des3_ede 0xffddb555acfd9d77b03ea3843f2653255afe8eb5573965df
	ip xfrm state get src 192.168.18.101 dst 192.168.18.102 proto esp spi 0x00000301

	ip xfrm policy add src 192.168.18.101 dst 192.168.18.102 dir out ptype main tmpl src 192.168.18.101 dst 192.168.18.102 proto esp mode tunnel
	ip xfrm policy add src 192.168.18.102 dst 192.168.18.101 dir in ptype main tmpl src 192.168.18.102 dst 192.168.18.101 proto esp mode tunnel
	ip xfrm policy ls
```

### 3、配置192.168.18.102

```
	ip xfrm state add src 192.168.18.101 dst 192.168.18.102 proto esp spi 0x00000301 mode tunnel auth md5 0x96358c90783bbfa3d7b196ceabe0536b enc des3_ede 0xf6ddb555acfd9d77b03ea3843f2653255afe8eb5573965df
	ip xfrm state add src 192.168.18.102 dst 192.168.18.101 proto esp spi 0x00000302 mode tunnel auth md5 0x99358c90783bbfa3d7b196ceabe0536b enc des3_ede 0xffddb555acfd9d77b03ea3843f2653255afe8eb5573965df
	ip xfrm state get src 192.168.18.101 dst 192.168.18.102 proto esp spi 0x00000301

	ip xfrm policy add src 192.168.18.101 dst 192.168.18.102 dir in ptype main tmpl src 192.168.18.101 dst 192.168.18.102 proto esp mode tunnel
	ip xfrm policy add src 192.168.18.102 dst 192.168.18.101 dir out ptype main tmpl src 192.168.18.102 dst 192.168.18.101 proto esp mode tunnel
	ip xfrm policy ls
```

### 4、测试4.1在192.168.18.101上执行
```
	ping 192.168.18.102
```

#### 4.2在192.168.18.102上抓包

```
	tcpdump -p esp
	tcpdump: verbose output suppressed, use -v or -vv for full protocol decode
	listening on eth0, link-type EN10MB (Ethernet), capture size 65535 bytes
	11:12:00.771364 IP 192.168.18.101 > 192.168.18.102: ESP(spi=0x00000301,seq=0x41d), length 116
	11:12:00.771498 IP 192.168.18.102 > 192.168.18.101: ESP(spi=0x00000302,seq=0x183), length 116
	11:12:01.773378 IP 192.168.18.101 > 192.168.18.102: ESP(spi=0x00000301,seq=0x41e), length 116
	11:12:01.773787 IP 192.168.18.102 > 192.168.18.101: ESP(spi=0x00000302,seq=0x184), length 116
	11:12:02.774682 IP 192.168.18.101 > 192.168.18.102: ESP(spi=0x00000301,seq=0x41f), length 116
	11:12:02.774793 IP 192.168.18.102 > 192.168.18.101: ESP(spi=0x00000302,seq=0x185), length 116
```


https://blog.csdn.net/xingyeping/article/details/51353448

