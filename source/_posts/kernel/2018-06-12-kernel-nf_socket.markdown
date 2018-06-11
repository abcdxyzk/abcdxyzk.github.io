---
layout: post
title: "nf_socket"
date: 2018-06-12 02:04:00 +0800
comments: false
categories:
- 2018
- 2018~06
- kernel
- kernel~proc
tags:
---

https://blog.csdn.net/jk110333/article/details/8642261

用户态与内核态交互通信的方法不止一种，sockopt是比较方便的一个，写法也简单. ipvsadm的两种通信方式之一

缺点就是使用 copy_from_user()/copy_to_user()完成内核和用户的通信， 效率其实不高， 多用在传递控制 选项 信息，不适合做大量的数据传输

#### 用户态函数：

发送：int setsockopt( int sockfd, int proto, int cmd, void *data, int datelen);

接收：int getsockopt(int sockfd, int proto, int cmd, void *data, int datalen);

第一个参数是socket描述符；

第二个参数proto是sock协议，IP RAW的就用SOL_SOCKET/SOL_IP等，TCP/UDP socket的可用SOL_SOCKET/SOL_IP/SOL_TCP/SOL_UDP等，即高层的socket是都可以使用低层socket的命令字 的，IPPROTO_IP；

第三个参数cmd是操作命令字，由自己定义；

第四个参数是数据缓冲区起始位置指针，set操作时是将缓冲区数据写入内核，get的时候是将内核中的数 据读入该缓冲区；

第五个参数数据长度

#### 内核态函数

注册：nf_register_sockopt(struct nf_sockopt_ops *sockops);

注销：nf_unregister_sockopt(struct nf_sockopt_ops *sockops);

结构体 nf_sockopt_ops test_sockops
```
	static struct nf_sockopt_ops nso = {
		.pf  = PF_INET,		// 协议族
		.set_optmin = 常数,	// 定义最小set命令字
		.set_optmax = 常数+N,	// 定义最大set命令字
		.set  = recv_msg,	// 定义set处理函数
		.get_optmin = 常数,	// 定义最小get命令字
		.get_optmax = 常数+N,	// 定义最大get命令字
		.get  = send_msg,	// 定义set处理函数
	};
```


其中命令字不能和内核已有的重复，宜大不宜小。命令字很重要，是用来做标识符的。而且用户态和内核态要定义的相同，
```
	#define SOCKET_OPS_BASE		128
	#define SOCKET_OPS_SET		(SOCKET_OPS_BASE)
	#define SOCKET_OPS_GET		(SOCKET_OPS_BASE)
	#define SOCKET_OPS_MAX		(SOCKET_OPS_BASE + 1)
```

set/get处理函数是直接由用户空间的 set/getsockopt函数调用的。 setsockopt函数向内核写数据，用getsockopt向内核读数据。
另外set和get的处理函数的参数应该是这样的

```
	int recv_msg(struct sock *sk, int cmd, void __user *user, unsigned int len);
	int send_msg(struct sock *sk, int cmd, void __user *user, unsigned int *len);
```

#### 用户态 setsockopt/getsockopt 调内核态 ipv4_specific.get/setsockopt -> ip_setsockopt -> nf_setsockopt -> nf_sockopt

内核态的module.c

```
	#include <linux/module.h>
	#include <linux/kernel.h>
	#include <linux/types.h>
	#include <linux/string.h>
	#include <linux/netfilter_ipv4.h>
	#include <linux/init.h>
	#include <asm/uaccess.h>

	#define SOCKET_OPS_BASE		(128+10000)
	#define SOCKET_OPS_SET		(SOCKET_OPS_BASE)
	#define SOCKET_OPS_GET		(SOCKET_OPS_BASE)
	#define SOCKET_OPS_MAX		(SOCKET_OPS_BASE + 1)

	#define KMSG			"--------kernel---------"
	#define KMSG_LEN		sizeof("--------kernel---------")


	static int recv_msg(struct sock *sk, int cmd, void __user *user, unsigned int len)
	{
		int ret = 0;
		printk(KERN_INFO "sockopt: recv_msg()\n");

		if (cmd == SOCKET_OPS_SET) {
			char umsg[64];
			int len = sizeof(char)*64;
			memset(umsg, 0, len);
			ret = copy_from_user(umsg, user, len);
			printk("recv_msg: umsg = %s. ret = %d\n", umsg, ret);
		}
		return 0;
	}

	static int send_msg(struct sock *sk, int cmd, void __user *user, int *len)
	{
		int ret = 0;
		printk(KERN_INFO "sockopt: send_msg()\n");
		if (cmd == SOCKET_OPS_GET) {
			ret = copy_to_user(user, KMSG, KMSG_LEN);
			printk("send_msg: umsg = %s. ret = %d. success\n", KMSG, ret);
		}
		return 0;

	}

	static struct nf_sockopt_ops test_sockops =
	{
		.pf = PF_INET,
		.set_optmin = SOCKET_OPS_SET,
		.set_optmax = SOCKET_OPS_MAX,
		.set = recv_msg,
		.get_optmin = SOCKET_OPS_GET,
		.get_optmax = SOCKET_OPS_MAX,
		.get = send_msg,
		.owner = THIS_MODULE,
	};

	static int __init init_sockopt(void)
	{
		printk(KERN_INFO "sockopt: init_sockopt()\n");
		return nf_register_sockopt(&test_sockops);
	}

	static void __exit exit_sockopt(void)
	{
		printk(KERN_INFO "sockopt: fini_sockopt()\n");
		nf_unregister_sockopt(&test_sockops);
	}

	module_init(init_sockopt);
	module_exit(exit_sockopt);
	MODULE_LICENSE("GPL");
```

用户态的user.c

```
	#include <unistd.h>
	#include <stdio.h>
	#include <sys/socket.h>
	#include <linux/in.h>
	#include <string.h>
	#include <errno.h>

	#define SOCKET_OPS_BASE		(128+10000)
	#define SOCKET_OPS_SET		(SOCKET_OPS_BASE)
	#define SOCKET_OPS_GET		(SOCKET_OPS_BASE)
	#define SOCKET_OPS_MAX		(SOCKET_OPS_BASE + 1)

	#define UMSG			"----------user------------"
	#define UMSG_LEN		sizeof("----------user------------")

	char kmsg[64];

	int main(void)
	{
		int sockfd;
		int len;
		int ret;

		sockfd = socket(AF_INET, SOCK_RAW, IPPROTO_RAW);
		if (sockfd < 0) {
			printf("can not create a socket\n");
			return -1;
		}

		/*call function recv_msg()*/
		ret = setsockopt(sockfd, IPPROTO_IP, SOCKET_OPS_SET, UMSG, UMSG_LEN);
		printf("setsockopt: ret = %d, msg = %s\n", ret, UMSG);
		len = sizeof(char)*64;

		/*call function send_msg()*/
		ret = getsockopt(sockfd, IPPROTO_IP, SOCKET_OPS_GET, kmsg, &len);
		printf("getsockopt: ret = %d, msg = %s\n", ret, kmsg);
		if (ret != 0) {
			printf("getsockopt error: errno = %d, errstr = %s\n", errno, strerror(errno));
		}

		close(sockfd);
		return 0;
	}
```

Makefile
```
	TARGET = socketopt
	OBJS = module.o

	KDIR = /lib/modules/`uname -r`/build
	PWD = $(shell pwd)

	obj-m := $(TARGET).o

	$(TARGET)-objs := $(OBJS)

	default:
		make -C $(KDIR) SUBDIRS=$(PWD) modules
	clean:
		-rm -rf *.o *.ko .$(TARGET).ko.cmd .*.flags *.mod.c modules.order  Module.symvers .tmp_versions
```
