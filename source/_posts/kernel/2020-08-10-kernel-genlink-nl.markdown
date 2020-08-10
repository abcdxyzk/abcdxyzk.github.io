---
layout: post
title: "数据交换 genlink, 使用nl库"
date: 2020-08-10 23:13:00 +0800
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
	
	#define MSG_KER "Hello from kernel space!!!"
	
	struct family_hdr {
		int ff;
	};
	
	struct data_hdr {
		int len;
		int kk;
		char str[0];
	};
	
	/* netlink attributes */
	enum {
		DOC_EXMPL_A_UNSPEC,
		DOC_EXMPL_A_MSG,
		DOC_EXMPL_A_MSG2,
		__DOC_EXMPL_A_MAX,
	};
	#define DOC_EXMPL_A_MAX (__DOC_EXMPL_A_MAX - 1)
	
	/* commands 定义命令类型，用户空间以此来表明需要执行的命令 */
	enum {
		DOC_EXMPL_C_UNSPEC,
		DOC_EXMPL_C_ECHO,
		__DOC_EXMPL_C_MAX,
	};
	#define DOC_EXMPL_C_MAX (__DOC_EXMPL_C_MAX - 1)
	
	static int nl_pre_doit(struct genl_ops*  ops, struct sk_buff* skb, struct genl_info* info)
	{
		return 0;
	}
	static void nl_post_doit(struct genl_ops*  ops, struct sk_buff* skb, struct genl_info* info)
	{
		return;
	}
	
	/* family definition */
	static struct genl_family nl_family = {
		.id = GENL_ID_GENERATE,   //这里不指定family ID，由内核进行分配
		.hdrsize = sizeof(struct family_hdr),             //自定义的头部长度，参考genl数据包结构
		.name = "DOC_EXMPL",      //这里定义family的名称，user program需要根据这个名字来找到对应的family ID。
		.version = 1,
		.maxattr = DOC_EXMPL_A_MAX,
		.pre_doit  = nl_pre_doit,
		.post_doit = nl_post_doit,
	};
	
	static int rcv_handler(struct genl_info *info)
	{
		struct nlattr *tb[DOC_EXMPL_A_MAX + 1] = {0};
		struct family_hdr *fhdr = (struct family_hdr *)info->userhdr;
		struct data_hdr *hdr;
		int rc, i;
		int rcvlen;
	
		//printk("kernel recv: %d %d %d %s\n", fhdr->ff, hdr->len, hdr->kk, hdr->str);
		//printk("gg %d\n", info->nlhdr->nlmsg_len - ((u64)info->userhdr - (u64)info->nlhdr) - sizeof(struct family_hdr));
		rc = nla_parse(tb, DOC_EXMPL_A_MAX,
				info->userhdr + sizeof(struct family_hdr),
				info->nlhdr->nlmsg_len - ((u64)info->userhdr - (u64)info->nlhdr) - sizeof(struct family_hdr),
				NULL);
		if (rc) {
			printk("nla_parse err\n");
			return -1;
		}
	
		for (i = 1; i <= DOC_EXMPL_A_MAX; i ++) {
			if (!tb[i])
				continue;
			rcvlen = nla_len(tb[i]);
			hdr = (struct data_hdr*)nla_data(tb[i]);
			printk("ker-rcv: %d %d %d %d rcvlen=%d %s\n", i, fhdr->ff, hdr->len, hdr->kk, rcvlen, hdr->str);
		}
		return 0;
	}
	
	
	static int snd_handler(struct genl_info *info)
	{
		struct sk_buff *reply = NULL;
		char sss[123];
		struct family_hdr *snd_fhdr;
		struct data_hdr *snd_hdr = (struct data_hdr *)sss;
		int err;
	
		reply = genlmsg_new(NLMSG_ALIGN(sizeof(struct family_hdr)) + NLMSG_ALIGN(sizeof(struct data_hdr)) + nla_total_size(strlen(MSG_KER)), GFP_KERNEL);
		if (!reply) {
			printk("genlmsg_new err\n");
			return -1;
		}
	
		snd_fhdr = (struct family_hdr *)genlmsg_put(reply, info->snd_portid, info->snd_seq, &nl_family, 0, DOC_EXMPL_C_ECHO);
		snd_fhdr->ff = 222;
		snd_hdr->len = 321;
		snd_hdr->kk = 654;
		strcpy(snd_hdr->str, MSG_KER);
		err = nla_put(reply, DOC_EXMPL_A_MSG, sizeof(struct data_hdr) + strlen(snd_hdr->str), snd_hdr);
		if (err) {
			printk("nla_put err\n");
			genlmsg_cancel(reply, snd_fhdr);
			return -2;
		}
	
		snd_hdr->len = 987;
		snd_hdr->kk = 789;
		err = nla_put(reply, DOC_EXMPL_A_MSG2, sizeof(struct data_hdr) + strlen(snd_hdr->str), snd_hdr);
		if (err) {
			printk("nla_put err2\n");
			genlmsg_cancel(reply, snd_fhdr);
			return -2;
		}
	
		genlmsg_end(reply, snd_fhdr);
	
		err = genlmsg_reply(reply, info);
		if (err)
			printk("genlmsg_reply err\n");
	
		return 0;
	}
	
	static int nl_echo(struct sk_buff *skb, struct genl_info *info)
	{
		rcv_handler(info);
		snd_handler(info);
		return 0;
	}
	
	static struct genl_ops nl_ops[] = {
		{
			.cmd	= DOC_EXMPL_C_ECHO,
			.doit	= nl_echo,
			.flags	= GENL_ADMIN_PERM,
		},
	};
	
	int genetlink_init(void)
	{
		return genl_register_family_with_ops(&nl_family, nl_ops, ARRAY_SIZE(nl_ops));
	}
	
	void genetlink_exit(void)
	{
		genl_unregister_family(&nl_family);
		printk("Generic Netlink_nl Example Module unloaded.\n");
	}
	
	module_init(genetlink_init);
	module_exit(genetlink_exit);
	MODULE_LICENSE("GPL");
```

```
	obj-m += genlink_nl.o
	
	KDIR := /usr/src/kernels/`uname -r`/
	
	PWD := `pwd`
	
	default:
		make -C $(KDIR) M=$(PWD) modules
	
	clean:
		rm -rf *.ko *.o *.mod.c .*.cmd .tmp_versions Module.symvers modules.order
```

### user

gcc user_nl.c -lnl

```
	// gcc user_nl.c -lnl
	//
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
	#include <asm/types.h>
	
	//#include <linux/genetlink.h>
	#include <netlink/socket.h>
	#include <netlink/genl/genl.h>
	#include <netlink/genl/family.h>
	#include <netlink/genl/ctrl.h>
	#include <netlink/msg.h>
	
	#define MESSAGE_TO_KERNEL   "Hello World from user space!!"
	
	struct family_hdr {
		int ff;
	};
	
	struct data_hdr {
		int len;
		int kk;
		char str[0];
	};
	
	/* netlink attributes */
	enum {
		DOC_EXMPL_A_UNSPEC,
		DOC_EXMPL_A_MSG,
		DOC_EXMPL_A_MSG2,
		__DOC_EXMPL_A_MAX,
	};
	#define DOC_EXMPL_A_MAX (__DOC_EXMPL_A_MAX - 1)
	
	//copy from kernel driver genl_ops's cmd
	enum {
		DOC_EXMPL_C_UNSPEC,
		DOC_EXMPL_C_ECHO,
		__DOC_EXMPL_C_MAX,
	};
	#define DOC_EXMPL_C_MAX (__DOC_EXMPL_C_MAX - 1)
	
	static int error_handler(struct sockaddr_nl *nla, struct nlmsgerr *err, void *arg)
	{
		int *ret = arg;
		fprintf(stderr, "error\n");
		*ret = err->error;
		return NL_STOP;
	}
	
	static int finish_handler(struct nl_msg *msg, void *arg)
	{
		int *ret = arg;
		*ret = 0;
		fprintf(stderr, "finish\n");
		return NL_SKIP;
	}
	
	static int ack_handler(struct nl_msg *msg, void *arg)
	{
		int *ret = arg;
		*ret = 0;
		fprintf(stderr, "ack\n");
		return NL_STOP;
	}
	
	static int reply_handler(struct nl_msg *msg, void *arg)
	{
		struct nlattr *tb[DOC_EXMPL_A_MAX + 1] = {0};
		struct genlmsghdr *gnlh = nlmsg_data(nlmsg_hdr(msg));
		struct family_hdr *fhdr = genlmsg_attrdata(gnlh, 0);
		struct data_hdr *hdr;
		int rc, i;
		int rcvlen;
	
		rc = nla_parse(tb, DOC_EXMPL_A_MAX,
				genlmsg_attrdata(gnlh, sizeof(struct family_hdr)),
				genlmsg_attrlen(gnlh, sizeof(struct family_hdr)), NULL);
		if (rc) {
			printf("nla_parse err\n");
			return NL_STOP;
		}
	
		for (i = 1; i <= DOC_EXMPL_A_MAX; i ++) {
			rcvlen = nla_len(tb[i]);
			hdr = (struct data_hdr*)nla_data(tb[i]);
			printf("rcv: %d %d %d %d rcvlen=%d %s\n", i, fhdr->ff, hdr->len, hdr->kk, rcvlen, hdr->str);
		}
		return NL_OK;
	}
	
	static int test_genlink_nl(void)
	{
		struct nl_handle *nlsk;
		int nlid, err, ret;
	
		struct nl_msg *msg = NULL;
		struct nl_cb *cb = NULL;
	
		char cbdata[123], sss[123];
		struct family_hdr *fhdr;
		struct data_hdr *hdr = (struct data_hdr *)sss;
	
		nlsk = nl_handle_alloc();
		if (!nlsk) {
			printf("nl_socket_alloc err\n");
			return -1;
		}
	
		nl_set_buffer_size(nlsk, 8192, 8192);
	
		if (genl_connect(nlsk)) {
			printf("genl_connect err\n");
			goto free_nlsk;
		}
	
		nlid = genl_ctrl_resolve(nlsk, "DOC_EXMPL");
		if (nlid < 0) {
			printf("genl_ctrl_resolve err\n");
			goto free_nlsk;
		}
	
		msg = nlmsg_alloc();
		if (!msg) {
			printf("nlmsg_alloc err\n");
			goto free_nlsk;
		}
	
		cb = nl_cb_alloc(NL_CB_DEFAULT);
		if (!cb) {
			printf("nl_cb_alloc err\n");
			goto free_msg;
		}
		nl_socket_set_cb(nlsk, cb);
	
		fhdr = (struct family_hdr *)genlmsg_put(msg, 0, 0, nlid, sizeof(struct family_hdr), 0, DOC_EXMPL_C_ECHO, 0);
		if (!fhdr) {
			printf("genlmsg_put err\n");
			goto free_cb;
		}
		fhdr->ff = 111;
	
		hdr->len = 123;
		hdr->kk = 456;
		strcpy(hdr->str, MESSAGE_TO_KERNEL);
		ret = nla_put(msg, DOC_EXMPL_A_MSG, sizeof(struct data_hdr)+strlen(MESSAGE_TO_KERNEL), hdr);
		if (ret < 0) {
			printf("nla_put err\n");
			goto free_cb;
		}
	
		hdr->len = 700;
		hdr->kk = 800;
		ret = nla_put(msg, DOC_EXMPL_A_MSG2, sizeof(struct data_hdr)+strlen(MESSAGE_TO_KERNEL), hdr);
		if (ret < 0) {
			printf("nla_put err2\n");
			goto free_cb;
		}
	
		ret = nl_send_auto_complete(nlsk, msg);
		if (ret < 0) {
			printf("nl_send_auto_complete err\n");
			goto free_cb;
		}
	
		err = 1;
		nl_cb_err(cb, NL_CB_CUSTOM, error_handler, &err);
		nl_cb_set(cb, NL_CB_FINISH, NL_CB_CUSTOM, finish_handler, &err);
		nl_cb_set(cb, NL_CB_ACK, NL_CB_CUSTOM, ack_handler, &err);
		nl_cb_set(cb, NL_CB_VALID, NL_CB_CUSTOM, reply_handler, &cbdata);
	
		ret = nl_recvmsgs(nlsk, cb);
		if (ret) {
			printf("nl_recvmsgs err\n");
		}
	
		return err;
	
	free_cb:
		nl_cb_put(cb);
	free_msg:
		nlmsg_free(msg);
	free_nlsk:
		nl_handle_destroy(nlsk);
		return -2;
	}
	
	int main(int argc, char *argv[])
	{
		return test_genlink_nl();
	}
```
