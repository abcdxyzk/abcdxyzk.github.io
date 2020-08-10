---
layout: post
title: "数据交换 netlink"
date: 2020-08-10 23:02:00 +0800
comments: false
categories:
- 2020
- 2020~08
- kernel
- kernel~proc
tags:
---

3.x 内核

### kernel

```
	#include <linux/kernel.h>
	#include <linux/module.h>
	#include <linux/types.h>
	#include <linux/sched.h>
	#include <net/sock.h>
	#include <net/netlink.h>
	
	#define NETLINK_TEST 29
	
	struct sock *nl_sk = NULL;
	
	void nl_data_ready(struct sk_buff *__skb)
	{
		struct sk_buff *skb;
		struct nlmsghdr *nlh;
		u32 pid;
		int rc;
		int len = NLMSG_SPACE(1200);
		char str[100];
	
		printk("net_link: data is ready to read.\n");
		skb = skb_get(__skb);
	
		if (skb->len >= NLMSG_SPACE(0)) {
			nlh = nlmsg_hdr(skb);
			printk("net_link: recv %s.\n", (char *)NLMSG_DATA(nlh));
			memcpy(str, NLMSG_DATA(nlh), sizeof(str));
			pid = nlh->nlmsg_pid; /*pid of sending process */
			printk("net_link: pid is %d\n", pid);
			kfree_skb(skb);
	
			skb = alloc_skb(len, GFP_ATOMIC);
			if (!skb) {
				printk(KERN_ERR "net_link: allocate failed.\n");
				return;
			}
			nlh = nlmsg_put(skb, 0, 0, 0, 1200, 0);
			NETLINK_CB(skb).portid = 0; /* from kernel */
	
			memcpy(NLMSG_DATA(nlh), str, sizeof(str));
			strcpy(NLMSG_DATA(nlh) + 10, " from kernel");
			printk("net_link: going to send.\n");
			rc = netlink_unicast(nl_sk, skb, pid, MSG_DONTWAIT);
			if (rc < 0) {
				printk(KERN_ERR "net_link: can not unicast skb (%d)\n", rc);
			}
			printk("net_link: send is ok.\n");
		}
		return;
	}
	
	static int test_netlink(void)
	{
		struct netlink_kernel_cfg cfg = {
			.groups		= 0,
			.input		= nl_data_ready,
			.cb_mutex	= NULL,
			.flags		= 0,
			.bind		= NULL,
		};
		nl_sk = netlink_kernel_create(&init_net, NETLINK_TEST, &cfg);
	
		if (!nl_sk) {
			printk(KERN_ERR "net_link: Cannot create netlink socket.\n");
			return -EIO;
		}
		printk("net_link: create socket ok.\n");
		return 0;
	}
	
	int netlink_init(void)
	{
		test_netlink();
		return 0;
	}
	
	void netlink_exit(void)
	{
		if (nl_sk != NULL) {
			sock_release(nl_sk->sk_socket);
		}
		printk("net_link: remove ok.\n");
	}
	
	module_init(netlink_init);
	module_exit(netlink_exit);
	MODULE_LICENSE("GPL");

```

```
	obj-m += netlink.o
	
	KDIR := /usr/src/kernels/`uname -r`/
	
	PWD := `pwd`
	
	default:
		make -C $(KDIR) M=$(PWD) modules
	
	clean:
		rm -rf *.ko *.o *.mod.c .*.cmd .tmp_versions Module.symvers modules.order
```

### user
```
	#include <sys/stat.h>
	#include <unistd.h>
	#include <stdio.h>
	#include <stdlib.h>
	#include <sys/socket.h>
	#include <sys/types.h>
	#include <string.h>
	#include <asm/types.h>
	#include <linux/netlink.h>
	#include <linux/socket.h>
	
	#define NETLINK_TEST 29
	
	#define MAX_PAYLOAD 1024 
	
	struct sockaddr_nl src_addr, dest_addr;
	struct nlmsghdr *nlh = NULL;
	struct iovec iov;
	int sock_fd;
	struct msghdr msg;
	
	int main(int argc, char* argv[])
	{
		sock_fd = socket(PF_NETLINK, SOCK_RAW, NETLINK_TEST);
	
		memset(&msg, 0, sizeof(msg));
		memset(&src_addr, 0, sizeof(src_addr));
		src_addr.nl_family = AF_NETLINK;
		src_addr.nl_pid = getpid(); 
		src_addr.nl_groups = 0; 
		bind(sock_fd, (struct sockaddr*)&src_addr, sizeof(src_addr));
	
		memset(&dest_addr, 0, sizeof(dest_addr));
		dest_addr.nl_family = AF_NETLINK;
		dest_addr.nl_pid = 0; 
		dest_addr.nl_groups = 0; 
	
		nlh = (struct nlmsghdr *)malloc(NLMSG_SPACE(MAX_PAYLOAD));
		nlh->nlmsg_len = NLMSG_SPACE(MAX_PAYLOAD);
		nlh->nlmsg_pid = getpid(); 
		nlh->nlmsg_flags = 0;
		strcpy(NLMSG_DATA(nlh), "Hello you!");
	
		iov.iov_base = (void *)nlh;
		iov.iov_len = nlh->nlmsg_len;
		msg.msg_name = (void *)&dest_addr;
		msg.msg_namelen = sizeof(dest_addr);
		msg.msg_iov = &iov;
		msg.msg_iovlen = 1;
	
		printf(" Sending message. ...\n");
		sendmsg(sock_fd, &msg, 0);
	
		memset(nlh, 0, NLMSG_SPACE(MAX_PAYLOAD));
		printf(" Waiting message. ...\n");
		recvmsg(sock_fd, &msg, 0);
		printf(" Received message payload: len=%d, data=%s\n", nlh->nlmsg_len, NLMSG_DATA(nlh));
	
		close(sock_fd);
		return 0;
	}
```
