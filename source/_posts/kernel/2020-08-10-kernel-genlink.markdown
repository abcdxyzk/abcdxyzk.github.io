---
layout: post
title: "数据交换 genlink"
date: 2020-08-10 23:09:00 +0800
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
	#include <linux/module.h>
	#include <net/netlink.h>
	#include <net/genetlink.h>
	#include <linux/version.h>
	
	#define TEST_GENL_MSG_FROM_KERNEL   "Hello from kernel space!!!"
	
	/* handler 
	 * message handling code goes here; return 0 on success, negative 
	 * values on failure 
	 */  
	static int doc_exmpl_echo(struct sk_buff *skb, struct genl_info *info);
	
	/* netlink attributes */
	enum {
		DOC_EXMPL_A_UNSPEC,
		DOC_EXMPL_A_MSG,
		__DOC_EXMPL_A_MAX,
	};
	#define DOC_EXMPL_A_MAX (__DOC_EXMPL_A_MAX - 1)
	
	/* attribute policy */
	static struct nla_policy doc_exmpl_genl_policy[DOC_EXMPL_A_MAX + 1] = {
		[DOC_EXMPL_A_MSG] = { .type = NLA_NUL_STRING },
	};
	
	/* commands 定义命令类型，用户空间以此来表明需要执行的命令 */
	enum {
		DOC_EXMPL_C_UNSPEC,
		DOC_EXMPL_C_ECHO,
		__DOC_EXMPL_C_MAX,
	};
	#define DOC_EXMPL_C_MAX (__DOC_EXMPL_C_MAX - 1)
	
	/* family definition */
	static struct genl_family doc_exmpl_genl_family = {
		.id = GENL_ID_GENERATE,   //这里不指定family ID，由内核进行分配
		.hdrsize = 0,             //自定义的头部长度，参考genl数据包结构
		.name = "DOC_EXMPL",      //这里定义family的名称，user program需要根据这个名字来找到对应的family ID。
		.version = 1,
		.maxattr = DOC_EXMPL_A_MAX,
	};
	
	/* operation definition 将命令command echo和具体的handler对应起来 */
	static struct genl_ops doc_exmpl_genl_ops_echo = {
		.cmd = DOC_EXMPL_C_ECHO,
		.flags = 0,
		.policy = doc_exmpl_genl_policy,
		.doit = doc_exmpl_echo,
		.dumpit = NULL,
	};
	
	static struct genl_multicast_group doc_exmpl_genl_mcgrp = {
		.name = "DOC_EXMPL_GRP",
	};
	
	static inline int genl_msg_prepare_usr_msg(u8 cmd, size_t size, pid_t pid, struct sk_buff **skbp)
	{
		struct sk_buff *skb;
	
		/* create a new netlink msg */
		skb = genlmsg_new(size, GFP_KERNEL);
		if (skb == NULL) {
			return -ENOMEM;
		}
	
		/* Add a new netlink message to an skb */
		genlmsg_put(skb, pid, 0, &doc_exmpl_genl_family, 0, cmd);
	
		*skbp = skb;
		return 0;
	}
	
	static inline int genl_msg_mk_usr_msg(struct sk_buff *skb, int type, void *data, int len)
	{
		int rc;
	
		/* add a netlink attribute to a socket buffer */
		if ((rc = nla_put(skb, type, len, data)) != 0) {
			return rc;
		}
		return 0;
	}
	
	/**
	* genl_msg_send_to_user - 通过generic netlink发送数据到netlink
	*
	* @data: 发送数据缓存
	* @len:  数据长度 单位：byte
	* @pid:  发送到的客户端pid
	*
	* return:
	*  0: 成功
	* -1: 失败
	*/
	int genl_msg_send_to_user(void *data, int len, pid_t pid)
	{
		struct sk_buff *skb;
		size_t size;
		void *head;
		int rc;
	
		size = nla_total_size(len); /* total length of attribute including padding */
	
		rc = genl_msg_prepare_usr_msg(DOC_EXMPL_C_ECHO, size, pid, &skb);
		if (rc) {
			return rc;
		}
	
		rc = genl_msg_mk_usr_msg(skb, DOC_EXMPL_A_MSG, data, len);
		if (rc) {
			kfree_skb(skb);
			return rc;
		}
	
		head = genlmsg_data(nlmsg_data(nlmsg_hdr(skb)));
	
		rc = genlmsg_end(skb, head);
		if (rc < 0) {
			kfree_skb(skb);
			return rc;
		}
	
		rc = genlmsg_unicast(&init_net, skb, pid);
		if (rc < 0) {
			return rc;
		}
	
		return 0;
	}
	
	//echo command handler, 命令处理函数，当接收到user program发出的命令后，这个函数会被内核调用
	static int doc_exmpl_echo(struct sk_buff *skb, struct genl_info *info)
	{
		/* message handling code goes here; return 0 on success, negative values on failure */
		struct nlmsghdr *nlhdr;
		struct genlmsghdr *genlhdr;
		struct nlattr *nlh;
		char *str;
		int ret;
	
		nlhdr = nlmsg_hdr(skb);
		genlhdr = nlmsg_data(nlhdr);
		nlh = genlmsg_data(genlhdr);
		str = nla_data(nlh);
		printk("doc_exmpl_echo get: nla_len=%d, nla_type=%d, %s\n", nlh->nla_len, nlh->nla_type, str);
	
		ret = genl_msg_send_to_user(TEST_GENL_MSG_FROM_KERNEL,
				strlen(TEST_GENL_MSG_FROM_KERNEL) + 1,  nlhdr->nlmsg_pid);
	
		return ret;
	}
	
	int genetlink_init(void)
	{
		int rc;
	
		/**
		 * 1. Registering A Family
		 * This function doesn't exist past linux 3.12
		 */
		rc = genl_register_family(&doc_exmpl_genl_family);
		if (rc != 0)
			goto err_out1;
	
		rc = genl_register_ops(&doc_exmpl_genl_family, &doc_exmpl_genl_ops_echo);
		if (rc != 0)
			goto err_out2;
	
		/*
		 * for multicast
		 */
		rc = genl_register_mc_group(&doc_exmpl_genl_family, &doc_exmpl_genl_mcgrp);
		if (rc != 0)
			goto err_out3;
	
		printk("doc_exmpl_genl_mcgrp.id=%d", doc_exmpl_genl_mcgrp.id);
		printk("genetlink_init OK");
		return 0;
	
	err_out3:
		genl_unregister_ops(&doc_exmpl_genl_family, &doc_exmpl_genl_ops_echo);
	err_out2:
		genl_unregister_family(&doc_exmpl_genl_family);
	err_out1:
		printk("Error occured while inserting generic netlink example module\n");
		return rc;
	}
	
	void genetlink_exit(void)
	{
		printk("Generic Netlink Example Module unloaded.");
	
		genl_unregister_mc_group(&doc_exmpl_genl_family, &doc_exmpl_genl_mcgrp);
		genl_unregister_ops(&doc_exmpl_genl_family, &doc_exmpl_genl_ops_echo);
		genl_unregister_family(&doc_exmpl_genl_family);
	}
	
	module_init(genetlink_init);
	module_exit(genetlink_exit);
	MODULE_LICENSE("GPL");
```

```
	obj-m += genlink.o
	
	KDIR := /usr/src/kernels/`uname -r`/
	
	PWD := `pwd`
	
	default:
		make -C $(KDIR) M=$(PWD) modules
	
	clean:
		rm -rf *.ko *.o *.mod.c .*.cmd .tmp_versions Module.symvers modules.order
```

### user

```
	#include <stdio.h>
	#include <stdlib.h>
	#include <errno.h>
	#include <unistd.h>
	#include <poll.h>
	#include <string.h>
	#include <fcntl.h>
	#include <sys/types.h>
	#include <sys/stat.h>
	#include <sys/socket.h>
	#include <sys/types.h>
	#include <signal.h>
	
	#include <linux/genetlink.h>
	
	#define GENLMSG_DATA(glh)	((void*)(((char*)glh) + GENL_HDRLEN))
	#define NLA_DATA(nla)		((void *)((char*)(nla) + NLA_HDRLEN))
	#define NLA_NEXT(nla,len)	((len) -= NLA_ALIGN((nla)->nla_len), \
					(struct nlattr*)(((char*)(nla)) + NLA_ALIGN((nla)->nla_len)))
	#define NLA_OK(nla,len)		((len) >= (int)sizeof(struct nlattr) && \
					(nla)->nla_len >= sizeof(struct nlattr) && \
					(nla)->nla_len <= (len))
	
	//copy from kernel driver genl_ops's cmd
	enum {
		DOC_EXMPL_C_UNSPEC,
		DOC_EXMPL_C_ECHO,
		__DOC_EXMPL_C_MAX,
	};
	
	//copy from kernel driver netlink attribute
	enum {
		DOC_EXMPL_A_UNSPEC,
		DOC_EXMPL_A_MSG,
		__DOC_EXMPL_A_MAX,
	};
	
	#define MESSAGE_TO_KERNEL   "Hello World from user space!"
	
	
	/**
	 * nla_attr_size - length of attribute size, NOT including padding
	 * @param payload   length of payload
	 * @return
	 */
	static inline int nla_attr_size(int payload)
	{
		return NLA_HDRLEN + payload;
	}
	
	/**
	 * nla_total_size - total length of attribute including padding
	 * @param payload   length of payload, NOT including NLA_HDR
	 */
	static inline int nla_total_size(int payload)
	{
		return NLA_ALIGN(nla_attr_size(payload));
	}
	
	static int genlmsg_open(void)
	{
		int sockfd;
		struct sockaddr_nl nladdr;
		int ret;
	
		sockfd = socket(AF_NETLINK, SOCK_RAW, NETLINK_GENERIC);
		if (sockfd < 0) {
			printf("socket: %m\n");
			return -1;
		}
	
		memset(&nladdr, 0, sizeof(nladdr));
		nladdr.nl_family = AF_NETLINK;
		nladdr.nl_pid = getpid();
		nladdr.nl_groups = 0xffffffff; //这个是mask值，如果family ID & nl_groups为0，
				   //则这个family的广播就接收不到，所以这里设为0xffffffff就可以接收所有的family消息
	
		ret = bind(sockfd, (struct sockaddr *)&nladdr, sizeof(nladdr));
		if (ret < 0) {
			printf("bind: %m\n");
			ret = -1;
			goto err_out;
		}
		return sockfd;
	
	err_out:
		close(sockfd);
		return ret;
	}
	
	static void *genlmsg_alloc(int *size)
	{
		unsigned char *buf;
		int len;
	
		/*
		 * attribute len
		 * attr len = (nla_hdr + pad) + (payload(user data) + pad)
		 */
		len = nla_total_size(*size);
		/*
		 * family msg len,
		 * but actually we have NOT custom family header
		 * family msg len = family_hdr + payload(attribute)
		 */
		len += 0;
		/*
		 * generic netlink msg len
		 * genlmsg len = (genlhdr + pad) + payload(family msg)
		 */
		len += GENL_HDRLEN;
		/*
		 * netlink msg len
		 * nlmsg len = (nlmsghdr + pad) + (payload(genlmsg) + pad)
		 */
		len = NLMSG_SPACE(len);
	
		buf = malloc(len);
		if (!buf)
			return NULL;
	
		memset(buf, 0, len);
		*size = len;
		return buf;
	}
	
	static void genlmsg_free(void *buf)
	{
		if (buf)
			free(buf);
	}
	
	static int genlmsg_send(int sockfd, unsigned short nlmsg_type, unsigned int nlmsg_pid,
			unsigned char genl_cmd, unsigned char genl_version,
			unsigned short nla_type, const void *nla_data, unsigned int nla_len)
	{
		struct nlmsghdr *nlh;      //netlink message header
		struct genlmsghdr *glh;    //generic netlink message header
		struct nlattr *nla;	   //netlink attribute header
		struct sockaddr_nl nladdr;
		unsigned char *buf;
		int len;
		int count;
		int ret;
	
		if ((nlmsg_type == 0) || (!nla_data) || (nla_len <= 0))
			return -1;
	
		len = nla_len;
		buf = genlmsg_alloc(&len);
		if (!buf)
			return -1;
	
		nlh = (struct nlmsghdr *)buf;
		nlh->nlmsg_len = len;
		nlh->nlmsg_type = nlmsg_type;
		nlh->nlmsg_flags = NLM_F_REQUEST;
		nlh->nlmsg_seq = 0;
		nlh->nlmsg_pid = nlmsg_pid;
	
		glh = (struct genlmsghdr *)NLMSG_DATA(nlh);
		glh->cmd = genl_cmd;
		glh->version = genl_version;
	
	
		nla = (struct nlattr *)GENLMSG_DATA(glh);
		nla->nla_type = nla_type;
		nla->nla_len = nla_attr_size(nla_len);
		memcpy(NLA_DATA(nla), nla_data, nla_len);
	
		memset(&nladdr, 0, sizeof(nladdr));
		nladdr.nl_family = AF_NETLINK;
	
		count = 0;
		ret = 0;
		do {
			ret = sendto(sockfd, &buf[count], len - count, 0,
				(struct sockaddr *)&nladdr, sizeof(nladdr));
			if (ret < 0) {
				if (errno != EAGAIN) {
					count = -1;
					goto out;
				}
			} else {
				count += ret;
			}
		} while (count < len);
	
	out:
		genlmsg_free(buf);
	
		printf("send len %d\n", count);
		return count;
	}
	
	/**
	 *
	 * @param sockfd	generic netlink socket fd
	 * @param buf	   the 'buf' is including the struct nlmsghdr,
	 *				  struct genlmsghdr and struct nlattr
	 * @param len	   size of 'buf'
	 * @return  >0	  size of genlmsg
	 *		  <0	  error occur
	 */
	static int genlmsg_recv(int sockfd, unsigned char *buf, unsigned int len)
	{
		struct sockaddr_nl nladdr;
		struct msghdr msg;
		struct iovec iov;
		int ret;
	
		nladdr.nl_family = AF_NETLINK;
		nladdr.nl_pid = getpid();
		nladdr.nl_groups = 0xffffffff;
	
		iov.iov_base = buf;
		iov.iov_len = len;
	
		msg.msg_name = (void *)&nladdr;
		msg.msg_namelen = sizeof(nladdr);
		msg.msg_iov = &iov;
		msg.msg_iovlen = 1;
		msg.msg_control = NULL;
		msg.msg_controllen = 0;
		msg.msg_flags = 0;
		ret = recvmsg(sockfd, &msg, 0);
		ret = ret > 0 ? ret : -1;
		printf("recv len %d\n", ret);
		return ret;
	}
	
	static int genlmsg_dispatch(struct nlmsghdr *nlmsghdr, unsigned int nlh_len,
				int nlmsg_type, int nla_type, unsigned char *buf, int *len)
	{
		struct nlmsghdr *nlh;
		struct genlmsghdr *glh;
		struct nlattr *nla;
		int nla_len;
	
		int l;
		int i;
		int ret = -1;
	
		if (!nlmsghdr || !buf || !len)
			return -1;
	
		printf("nlmsg_type = %d\n", nlmsghdr->nlmsg_type);
		if (nlmsg_type && (nlmsghdr->nlmsg_type != nlmsg_type))
			return -1;
	
		//读取到的数据流里面，可能会包含多条nlmsg
		for (nlh = nlmsghdr; NLMSG_OK(nlh, nlh_len); nlh = NLMSG_NEXT(nlh, nlh_len))
		{
			/* The end of multipart message. */
			if (nlh->nlmsg_type == NLMSG_DONE) {
				printf("get NLMSG_DONE\n");
				ret = 0;
				break;
			}
	
			if (nlh->nlmsg_type == NLMSG_ERROR) {
				printf("get NLMSG_ERROR\n");
				ret = -1;
				break;
			}
	
			glh = (struct genlmsghdr *)NLMSG_DATA(nlh);
			nla = (struct nlattr *)GENLMSG_DATA(glh);   //the first attribute
			nla_len = nlh->nlmsg_len - GENL_HDRLEN;		   //len of attributes
			for (i = 0; NLA_OK(nla, nla_len); nla = NLA_NEXT(nla, nla_len), ++i) {
				//一条nlmsg里面，可能会包含多个attr
				printf("%d. nla->nla_type = %d\n", i, nla->nla_type);
				/* Match the family ID, copy the data to user */
				if (nla_type == nla->nla_type) {
					l = nla->nla_len - NLA_HDRLEN;  //attribute里的payload就是内核返回给用户的实际数据
					*len = *len > l ? l : *len;
					memcpy(buf, NLA_DATA(nla), *len);
					ret = 0;
					break;
				}
			}
		}
		return ret;
	}
	
	static int genlmsg_get_family_id(int sockfd, const char *family_name)
	{
		void *buf;
		int len;
		__u16 id;
		int l;
		int ret;
	
		ret = genlmsg_send(sockfd, GENL_ID_CTRL, 0, CTRL_CMD_GETFAMILY, 1,
				CTRL_ATTR_FAMILY_NAME, family_name, strlen(family_name) + 1);
		if (ret < 0)
			return -1;
	
		len = 256;
		buf = genlmsg_alloc(&len);
		if (!buf)
			return -1;
	
		len = genlmsg_recv(sockfd, buf, len);
		if (len < 0)
			return len;
	
		id = 0;
		l = sizeof(id);
		genlmsg_dispatch((struct nlmsghdr *)buf, len, 0, CTRL_ATTR_FAMILY_ID, (unsigned char *)&id, &l);
		genlmsg_free(buf);
	
		return id > 0 ? id : -1;
	}
	
	
	#define BUF_SIZE	256
	static int test_netlink_unicast(void)
	{
		struct nlmsghdr *nlh = NULL;
		int sockfd = -1;
		unsigned char buf[BUF_SIZE];
		int len;
		int id;
		pid_t pid;
		int ret;
	
		len = BUF_SIZE;
		nlh = genlmsg_alloc(&len);
		if (!nlh)
			return -1;
	
		sockfd = genlmsg_open();
		if (sockfd < 0)
			return -1;
	
		id = genlmsg_get_family_id(sockfd, "DOC_EXMPL");  //这里必须先通过family的名字获取到family ID，名字需要与驱动里的一致
		printf("get family ID[%d]\n", id);
		if (id <= 0) {
			ret = -1;
			goto out;
		}
	
		pid = getpid();
		ret = genlmsg_send(sockfd, id, pid, DOC_EXMPL_C_ECHO, 1,
				DOC_EXMPL_A_MSG, MESSAGE_TO_KERNEL, strlen(MESSAGE_TO_KERNEL) + 1); //向内核发送genl消息
		if (ret < 0)
			goto out;
	
		ret = genlmsg_recv(sockfd, (unsigned char *)nlh, len); //等待内核的回复
		if (ret > 0) {
			memset(buf, 0, sizeof(buf));
			len = sizeof(buf);
			ret = genlmsg_dispatch(nlh, ret, id, DOC_EXMPL_A_MSG, buf, &len);
			if (ret == 0) {
				printf("get: %s\n", buf);
			}
		}
	
	out:
		close(sockfd);
		genlmsg_free(nlh);
		return ret;
	}
	
	int main(int argc, char *argv[])
	{
		test_netlink_unicast();
		return 0;
	}
```
