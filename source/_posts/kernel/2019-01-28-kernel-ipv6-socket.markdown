---
layout: post
title: "IPv6 socket"
date: 2019-01-28 03:56:00 +0800
comments: false
categories:
- 2019
- 2019~01
- kernel
- kernel~net
tags:
- ipv6
---

https://blog.csdn.net/u013401853/article/details/55002655

#### server_ip6.c

```
	#include <stdio.h>
	#include <stdlib.h>
	#include <string.h>
	#include <sys/types.h>
	#include <sys/socket.h>
	#include <netinet/in.h>
	#include <unistd.h>

	#define BUF_LEN 2048
	#define PORT 8080

	int main(int argc, char *argv[])
	{
		int serv_sock = -1, client_sock = -1;
		socklen_t addr_len = 0;
		struct sockaddr_in6 local_addr = {0}, client_addr = {0};
		char buf[BUF_LEN] = {0};
		int err = -1;

		/* 建立socket */
		serv_sock = socket(PF_INET6, SOCK_STREAM, 0);
		if (-1 == serv_sock) {
			perror("socket error: ");
			return -1;
		}

		/* 填充地址结构 */
		local_addr.sin6_family = AF_INET6;
		local_addr.sin6_port = htons(PORT);
		local_addr.sin6_addr = in6addr_any;

		/* 绑定地址 */
		err = bind(serv_sock, (struct sockaddr *)&local_addr, sizeof(struct sockaddr_in6));
		if (-1 == err) {
			perror("bind error: ");
			close(serv_sock);
			return -1;
		}

		/* 监听 */
		err = listen(serv_sock, 5);
		if (-1 == err) {
			perror("listen error: ");
			close(serv_sock);
			return -1;
		}

		/* 循环等待客户连接请求 */
		while (1) {
			memset(&client_addr, 0x0, sizeof(client_addr));
			addr_len = sizeof(struct sockaddr_in6);
			client_sock = accept(serv_sock, (struct sockaddr *)&client_addr, &addr_len);
			if (-1 == client_sock) {
				perror("accept error:");
				close(serv_sock);
				return -1;
			}

			/* 转换client地址为字符串并打印 */
			inet_ntop(AF_INET6, &client_addr.sin6_addr, buf, BUF_LEN);
			printf("A clinet connected, ip: %s, port %d\n", buf, ntohs(client_addr.sin6_port));

			/* 接收消息 */
			memset(buf, 0x0, BUF_LEN);
			err = recv(client_sock, buf, BUF_LEN, 0);
			if (err < 0) {
				perror("recv error:");
				close(serv_sock);
				close(client_sock);
				return -1;
			}
			printf("recv %d bytes: %s\n", err, buf);

			/* 回送消息 */
			err = send(client_sock, buf, strlen(buf), 0);
			if (err < 0) {
				perror("send error:");
				close(serv_sock);
				close(client_sock);
				return -1;
			}

			/* 关闭这个client连接 */
			close(client_sock);
		}
		return 0;
	}
```

#### client_ip6.c

```
	#include <stdio.h>
	#include <stdlib.h>
	#include <string.h>
	#include <sys/types.h>
	#include <sys/socket.h>
	#include <netinet/in.h>
	#include <unistd.h>

	#define BUF_LEN 2048
	#define PORT 8080

	int main(int argc, char *argv[])
	{
		int sock = -1;
		socklen_t addr_len = 0;
		struct sockaddr_in6 serv_addr = {0};
		char buf[BUF_LEN] = {0};
		int err = -1;

		/* 建立socket */
		sock = socket(AF_INET6, SOCK_STREAM, 0);
		if (-1 == sock) {
			perror("socket error: ");
			return -1;
		}

		memset(&serv_addr, 0x0, sizeof(serv_addr));
		/* 填充地址结构 */
		serv_addr.sin6_family = AF_INET6;
		serv_addr.sin6_port = htons(PORT);

		//serv_addr.sin6_addr = in6addr_loopback;  /* 连接到环回地址 */

		//inet_pton(AF_INET6, "2002:da80:e000::1:1:9", &serv_addr.sin6_addr);

		//inet_pton(AF_INET6, "::ffff:c0a8:0208", &serv_addr.sin6_addr);
		//inet_pton(AF_INET6, "::c0a8:0205", &serv_addr.sin6_addr);

		// connect到链路本地地址，需要设置sin6_scope_id，用`ip addr show`获取
		serv_addr.sin6_scope_id = 2;
		inet_pton(AF_INET6, "fe80::a00:27ff:fea0:67d6", &serv_addr.sin6_addr);

		addr_len = sizeof(serv_addr);
		err = connect(sock, (struct sockaddr *)&serv_addr, addr_len);
		if (-1 == err) {
			perror("connect error:");
			close(sock);
			return -1;
		}

		/* 发送消息 */
		memset(buf, 0x0, BUF_LEN);
		snprintf(buf, BUF_LEN - 1, "hello server, I'm client\n");
		err = send(sock, buf, strlen(buf), 0);
		if (err < 0) {
			perror("send error:");
			close(sock);
			return -1;
		}

		/* 接收消息 */
		memset(buf, 0x0, BUF_LEN);
		err = recv(sock, buf, BUF_LEN, 0);
		if (err < 0) {
			perror("recv error:");
			close(sock);
			return -1;
		}
		printf("recv %d bytes: %s\n", err, buf);

		close(sock);

		return 0;
	}
```
