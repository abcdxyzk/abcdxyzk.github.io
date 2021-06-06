---
layout: post
title: "SOCKS5 协议"
date: 2021-06-06 21:44:00 +0800
comments: false
categories:
- 2021
- 2021~06
- tools
- tools~socksvpn
tags:
- vpn
---

http://www.moye.me/2017/08/03/analyze-socks5-protocol/

SOCKS5 协议并不负责代理服务器的数据传输环节，此协议只是在C/S两端真实交互之间，建立起一条从客户端到代理服务器的授信连接。来看看细节：

### 协议流程

从流程上来说，SOCKS5  是一个C/S 交互的协议，交互大概分为这么几步：

```
	客户端发送认证协商
	代理服务器就认证协商进行回复（如拒绝则本次会话结束）
		如需GSSAPI或用户名/密码认证，客户端发送认证信息
		代理服务器就对应项进行鉴权，并进行回复或拒绝
	客户端发送希望连接的目标信息
	代理服务器就连接信息进行确认或拒绝
	【非协议内容】：代理服务器连接目标并 pipe 到客户端
```

### 协议细节
#### 1. 认证

认证方法：
```
	0x00: NO AUTHENTICATION REQUIRED
	0x01: GSSAPI
	0x02: USERNAME/PASSWORD
	0x03: to X’7F’ IANA ASSIGNED
	0x80: to X’FE’ RESERVED FOR PRIVATE METHODS
	0xFF: NO ACCEPTABLE METHODS
```

#### 1.1 客户端 -> 代理服务器，请求认证：
<table>
<tr><th>版本号(1字节)</th><th>可供选认证方法(1字节)</th><th>选择的方法(1~255字节)</th></tr>
<tr><td>固定为5</td><td>选了多少种</td><td>都有上表中哪些方法</td></tr>
</table>

#### 1.2 代理服务器  -> 客户端，响应认证：
<table>
<tr><th>版本号(1字节)</th><th>确认认证的方法</th></tr>
<tr><td>固定为5</td><td>认证方法列表的某项：<br>
0x00，则无需客户端发送进一步认证的信息<br>
0x01，则需要客户端进行进一步认证，细节见 RFC1929<br>
0x01，则需要客户端进行进一步认证，细节见RFC2743<br>
0xFF，则相当于拒绝请求，客户端只能关闭连接<br>
</td></tr>
</table>

#### 2. 请求信息
#### 2.1 客户端 -> 代理服务器，发送目标信息：

<table>
<tr><th>版本号(1字节)</th><th>命令(1字节)</th><th>保留(1字节)</th><th>请求类型(1字节)</th><th>地址(不定长)</th><th>端口(2字节)</th></tr>
<tr><td>固定为5</td><td>0x01: CONNECT<br>
0x02: BIND<br>
0x03: UDP ASSOCIATE</td>
<td>固定为 0x00</td><td>0x01: IP V4 地址<br>
0x03: 域名<br>
0x04: IP V6 地址<br></td>
<td>如果请求类型是域名，<br>
第个1字节为
域名的长度</td><td></td></tr>
</table>	


命令字段说明：
```
	CONNECT:  用于客户端请求服务器进行代理
	BIND:  用于客户端向服务器上报自己的反向连接监听地址（应用场景如 FTP 下载，客户端需要接受来自服务器的连接
	UDP ASSOCIATE：用于请求建立到 UDP 数据报中继的连接
```

#### 2.2 代理服务器 -> 客户端，确认连接：
<table>
<tr><th>版本号(1字节)</th><th>确认回应(1字节)</th><th>保留(1字节)</th><th>响应类型(1字节)</th><th>地址(不定长)</th><th>端口(2字节)</th></tr>
<tr><td>固定为5</td><td>0x00: succeeded<br>
0x01: general SOCKS server failure<br>
0x02: connection not allowed by ruleset<br>
0x03: Network unreachable<br>
0x04: Host unreachable<br>
0x05: Connection refused<br>
0x06: TTL expired<br>
0x07: Command not supported<br>
0x08: Address type not supported<br>
0x09: to X’FF’ unassigned</td>
<td>固定为 0x00</td>
<td>仅用于响应客<br>
户端BIND命令：<br>
0x01: IP V4 地址<br>
0x03: 域名<br>
0x04: IP V6 地址</td>
<td>仅用于响应客<br>
户端BIND命令：<br>
如果请求<br>
类型是域名，<br>
第个1字节为<br>
域名的长度<br>
</td>
<td>仅用于响应客<br>
户端BIND命令</td></tr>
</table>

可以看出，在代理服务器确认回应为 0x00 时，此次 SOCKS5 协议协商部分顺利完成，宣告进入到数据传输阶段（也可以说，这之后发生的事已经与SOCKS5协议无关）。

