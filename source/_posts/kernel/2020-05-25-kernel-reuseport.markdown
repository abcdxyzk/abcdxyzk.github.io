---
layout: post
title: "reuseport使用"
date: 2020-05-25 16:57:00 +0800
comments: false
categories:
- 2020
- 2020~05
- kernel
- kernel~net
tags:
---

https://www.cnblogs.com/Anker/p/7076537.html

#### SO_REUSEPORT解决了什么问题

SO_REUSEPORT支持多个进程或者线程绑定到同一端口，提高服务器程序的性能，解决的问题：

允许多个套接字 bind()/listen() 同一个TCP/UDP端口  
  每一个线程拥有自己的服务器套接字  
  在服务器套接字上没有了锁的竞争  

内核层面实现负载均衡

安全层面，监听同一个端口的套接字只能位于同一个用户下面


其核心的实现主要有三点：

  扩展 socket option，增加 SO_REUSEPORT 选项，用来设置 reuseport。

  修改 bind 系统调用实现，以便支持可以绑定到相同的 IP 和端口

  修改处理新建连接的实现，查找 listener 的时候，能够支持在监听相同 IP 和端口的多个 sock 之间均衡选择。

有了SO_RESUEPORT后，每个进程可以自己创建socket、bind、listen、accept相同的地址和端口，各自是独立平等的。让多进程监听同一个端口，各个进程中accept socket fd不一样，有新连接建立时，内核只会唤醒一个进程来accept，并且保证唤醒的均衡性。

#### 可优化 ？？

在 `___inet_lookup_listener` -> compute_score() 中，每个cpu只选特定的sk，这样能减少锁竞争和cache吗 ？？？

#### 代码

```
	#define _GNU_SOURCE
	
	#include <stdio.h>
	#include <unistd.h>
	#include <sys/types.h>
	#include <sys/socket.h>
	#include <netinet/in.h>
	#include <arpa/inet.h>
	#include <assert.h>
	#include <sys/wait.h>
	#include <string.h>
	#include <errno.h>
	#include <stdlib.h>
	#include <fcntl.h>
	
	
	#include <sched.h>
	#include <pthread.h>
	
	#include <netinet/tcp.h>
	
	#define IP		"192.168.3.6"
	#define PORT		80
	#define WORKER		8
	#define MAXLINE		4096
	
	
	int worker(int i)
	{
		struct sockaddr_in address;
		bzero(&address, sizeof(address));
		address.sin_family = AF_INET;
		inet_pton( AF_INET, IP, &address.sin_addr);
		address.sin_port = htons(PORT);
	
		pid_t pid = getpid();
		unsigned cc = 0;
		cpu_set_t mask;
		CPU_ZERO(&mask);
		CPU_SET(i, &mask);
	
		printf("pid=%d %d\n", pid, i);
		if (sched_getaffinity(pid, sizeof(mask), &mask) < 0) {
			printf("sched_getaffinity err\n");
		}
	
		int listenfd = socket(PF_INET, SOCK_STREAM, 0);
		assert(listenfd >= 0);
	
		int val =1;
		/*set SO_REUSEPORT*/
		if (setsockopt(listenfd, SOL_SOCKET, SO_REUSEPORT, &val, sizeof(val)) < 0) {
			perror("setsockopt()");
		}
	
		val = 1000 + i;
		if (setsockopt(listenfd, SOL_TCP, TCP_MAXSEG, &val, sizeof(val))<0) {
			perror("setsockopt()");
		}
	
		int ret = bind(listenfd, (struct sockaddr*)&address, sizeof(address));
		assert(ret != -1);
	
		ret = listen(listenfd, 5);
		assert(ret != -1);
		while (1) {
			cc ++;
			if (cc % 100 == 0)
				printf("thread=%d cc=%d\n", i, cc);
	//		printf("I am worker %d, begin to accept connection.\n", i);
			struct sockaddr_in client_addr;
			socklen_t client_addrlen = sizeof( client_addr );
			int connfd = accept(listenfd, (struct sockaddr*)&client_addr, &client_addrlen);
			if (connfd != -1) {
	//			printf("worker %d accept a connection success. ip:%s, prot:%d\n", i, inet_ntoa(client_addr.sin_addr), client_addr.sin_port);
			} else {
	//			printf("worker %d accept a connection failed,error:%s", i, strerror(errno));
			}
			char buffer[MAXLINE];
	//		int nbytes = read(connfd, buffer, MAXLINE);
	//		printf("read from client is:%s\n", buffer);
	//		write(connfd, buffer, nbytes);
			close(connfd);
		}
		return 0;
	}
	
	int main()
	{
		int i = 0;
		for (i = 0; i < WORKER; i++) {
			printf("Create worker %d\n", i);
			pid_t pid = fork();
			/*child  process */
			if (pid == 0) {
				worker(i);
			}
			if (pid < 0) {
				printf("fork error");
			}
		}
		/*wait child process*/
		while (wait(NULL) != 0)
			;
		if (errno == ECHILD) {
			fprintf(stderr, "wait error:%s\n", strerror(errno));
		}
		return 0;
	}
```

