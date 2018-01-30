---
layout: post
title: "TCP Fast Open(TFO), tcp_fastopen"
date: 2018-01-25 03:25:00 +0800
comments: false
categories:
- 2018
- 2018~01
- kernel
- kernel~net
tags:
---

https://www.2cto.com/kf/201701/586043.html

http://blog.csdn.net/u011130578/article/details/44515165

http://blog.sina.com.cn/s/blog_583f42f101011veh.html

```
	#define TFO_CLIENT_ENABLE       1
	#define TFO_SERVER_ENABLE       2
	#define TFO_CLIENT_NO_COOKIE    4       /* Data in SYN w/o cookie option */

	/* Process SYN data but skip cookie validation */
	#define TFO_SERVER_COOKIE_NOT_CHKED     0x100	// 收到cookie也不检查
	/* Accept SYN data w/o any cookie option */
	#define TFO_SERVER_COOKIE_NOT_REQD      0x200	// 不需要cookie需要data就能创建fastopen child，默认情况下syn的data会被忽略

	/* Force enable TFO on all listeners, i.e., not requiring the
	 * TCP_FASTOPEN socket option. SOCKOPT1/2 determine how to set max_qlen.
	 */
	#define TFO_SERVER_WO_SOCKOPT1  0x400		// 调listen后不需要再调setsockopt就开启fastopen
	#define TFO_SERVER_WO_SOCKOPT2  0x800		// 调listen后不需要再调setsockopt就开启fastopen，backlog=TFO_SERVER_WO_SOCKOPT2>>16
	/* Always create TFO child sockets on a TFO listener even when
	 * cookie/data not present. (For testing purpose!)
	 */
	#define TFO_SERVER_ALWAYS       0x1000		// 不需要cookie也不需要data就创建fastopen child, 容易被攻击，不开启
```

### 测试

#### 开启
```
	echo 3 > /proc/sys/net/ipv4/tcp_fastopen   # 1 开启客户端，2 开启服务端，3 都开启

	tc qdisc add dev lo root netem delay 300ms # 设置延迟才能看出效果
	ifconfig lo mtu 1500
```

#### client
```
	#include <stdio.h>
	#include <unistd.h>
	#include <string.h>
	#include <errno.h>
	#include <sys/types.h>
	#include <sys/socket.h>
	#include <netinet/in.h>
	
	#include <netinet/tcp.h>
	
	#ifndef MSG_FASTOPEN
	#define MSG_FASTOPEN   0x20000000
	#endif
	
	int main(int argc, char *argv[])
	{
		int sockfd, n;
		struct sockaddr_in servaddr;
		char buf[50000] = "aaabbbccc";
		int ret = 0, tot;
	
		if ((sockfd = socket(AF_INET, SOCK_STREAM, 0)) < 0) {
			printf ("create socket error: %s(errno: %d)\n", strerror (errno), errno);
			return -1;
		}
	
		memset (&servaddr, 0, sizeof (servaddr));
		servaddr.sin_family = AF_INET;
		servaddr.sin_port = htons (1935);
		servaddr.sin_addr.s_addr = inet_addr("127.0.0.1");
	
	#define FASTOPEN_TEST
	#ifndef FASTOPEN_TEST
		if (connect(sockfd, (struct sockaddr *)&servaddr, sizeof(servaddr))) {
			printf("connect error\n");
			return -2;
		}
		ret = send(sockfd, buf, 1005, 0);
	#else
		ret = sendto(sockfd, buf, 1005, MSG_FASTOPEN, (struct sockaddr *)&servaddr, sizeof(servaddr));
	#endif
		if (ret < 0) {
			printf ("send msg error: %s(errno: %d)\n", strerror (errno), errno);
			// 如果是连接失败会打印：Connection refused(errno: 111)
			return -2;
		}
		printf("client fastopen sendto len=%d\n", ret);
		if ((ret = send(sockfd, buf, 20000, 0)) < 0) {
			printf("send error ret = %d\n", ret);
		}
		printf("client send len = %d\n", ret);
		shutdown(sockfd, 1);
	
		tot = 0;
		while ((n = recv(sockfd, buf, 1024, 0)) > 0)
			tot += n;
		printf("client recv len = %d\n", tot);
		close (sockfd);
		return 0;
	}
```

#### server
```
	#include <unistd.h>
	#include <string.h>
	#include <sys/types.h>
	#include <sys/socket.h>
	#include <netinet/in.h>
	#include <netinet/tcp.h>
	#include <stdio.h>
	
	int main()
	{
		int serverSock, clientSock;
		struct sockaddr_in addr, clientAddr;
		int addrLen;
	
		char buf[10240];
		int n, tot;
	
		serverSock = socket(AF_INET, SOCK_STREAM, 0);
		if (serverSock == -1) {
			printf("socket failed!\n");
			return -1;
		}
	
		memset(&addr, 0, sizeof(addr));
		addr.sin_family = AF_INET;
		addr.sin_port = htons(1935);
		addr.sin_addr.s_addr = inet_addr("127.0.0.1");
	
		if (bind(serverSock, (struct sockaddr*)&addr, sizeof(addr)) < 0) {
			printf("bind failed!\n");
			return -2;
		}
	
		int qlen = 5;
		setsockopt(serverSock, SOL_TCP, TCP_FASTOPEN, &qlen, sizeof(qlen));
	
		if (listen(serverSock, 511) < 0) {
			printf("listen failed!\n");
			return -3;
		}
	
		while (1) {
			addrLen = sizeof(clientAddr);
			clientSock = accept(serverSock, (struct sockaddr*)&clientAddr, &addrLen);
			if (clientSock < 0) {
				printf("accept failed!\n");
				return -4;
			}
	
			if ((n = send(clientSock, buf, 10000, 0)) < 0) {
				printf("send error ret = %d\n", n);
				return -5;
			}
			printf("server send len = %d\n", n);
			shutdown(clientSock, 1);
	
			sleep(1);
	
			tot = 0;
			while ((n = recv(clientSock, buf, 1024, 0)) > 0)
				tot += n;
			printf("server recv len = %d\n", tot);
			close(clientSock);
		}
	
		return 0;
	}
```

### 原理

1.客户端发送一个SYN包到服务器，这个包中携带了Fast Open Cookie Request;

2.服务器生成一个cookie，这个cookie是加密客户端的IP地址生成的。服务器给客户端发送SYN+ACK响应，在响应包的选项中包含了这个cookie;

3.客户端存储这个cookie以便将来再次与这个服务器的IP建立TFO连接时使用;

也就是说，第一次TCP连接只是交换cookie信息，无法在SYN包中携带数据。在第一次交换之后，接下来的TCP连接就可以在SYN中携带数据了。流程如下：

4.客户端发送一个SYN包，这个包比较特殊，因为它携带应用数据和cookie;

5.服务器验证这个cookie，如果合法，服务器发送一个SYN+ACK，这个ACK同时确认SYN和数据。然后数据被传递到应用进程;

如果不合法，服务器丢弃数据，发送一个SYN+ACK，这个ACK只确认SYN，接下来走三次握手的普通流程;

6.如果验证合法(接收了SYN包中的数据)，服务器在接收到客户端的第一个ACK前可以发送其它响应数据;

7.如果验证不合法(客户端在SYN中带的数据没被确认)，客户端发送ACK确认服务器的SYN;并且，数据会在ACK包中重传;

8.下面的流程与普通的TCP交互流程无异。

![](/images/kernel/2018-01-25.jpeg)


### 源码分析
TFO功能在Linux 2.6.34内核中开始集成。

下面通过分析内核代码来了解TFO的运行机制。开启TFO功能后，server端进程在调用listen系统调用时会初始化TFO队列：

```
	int inet_listen(struct socket *sock, int backlog)
	{
		struct sock *sk = sock->sk;
		unsigned char old_state;
		int err;
		...
		if (old_state != TCP_LISTEN) {
		...
			if ((sysctl_tcp_fastopen & TFO_SERVER_ENABLE) != 0 &&
				inet_csk(sk)->icsk_accept_queue.fastopenq == NULL) {
				if ((sysctl_tcp_fastopen & TFO_SERVER_WO_SOCKOPT1) != 0)
					err = fastopen_init_queue(sk, backlog);
				else if ((sysctl_tcp_fastopen &
					  TFO_SERVER_WO_SOCKOPT2) != 0)
					err = fastopen_init_queue(sk,
						((uint)sysctl_tcp_fastopen) >> 16);
				else
					err = 0;
				if (err)
					goto out;
			}
			err = inet_csk_listen_start(sk, backlog);
...
```

fastopen_init_queue函数
```
	static inline int fastopen_init_queue(struct sock *sk, int backlog)
	{
		struct request_sock_queue *queue =
			&inet_csk(sk)->icsk_accept_queue;
	
		if (queue->fastopenq == NULL) {
			queue->fastopenq = kzalloc(
				sizeof(struct fastopen_queue),
				sk->sk_allocation);
			if (queue->fastopenq == NULL)
				return -ENOMEM;
	
			sk->sk_destruct = tcp_sock_destruct;
			spin_lock_init(&queue->fastopenq->lock);
		}
		queue->fastopenq->max_qlen = backlog;
		return 0;
	}
```

如果net.ipv4.tcp_fastopen && (TFO_SERVER_WO_SOCKOPT1|TFO_SERVER_WO_SOCKOPT2)为假，则TFO队列不会被初始化。但setsockopt函数也可以初始化TFO队列：

```
	static int do_tcp_setsockopt(struct sock *sk, int level,
			int optname, char __user *optval, unsigned int optlen)
	{
		struct tcp_sock *tp = tcp_sk(sk);
		struct inet_connection_sock *icsk = inet_csk(sk);
		int val;
		int err = 0;
		...
		case TCP_FASTOPEN:
			if (val >= 0 && ((1 << sk->sk_state) & (TCPF_CLOSE |
				TCPF_LISTEN)))
				err = fastopen_init_queue(sk, val);
			else
				err = -EINVAL;
			break;
		...
```

如果inet_csk(sk)->icsk_accept_queue.fastopenq为NULL的话意味着TFO功能未开启。

轮到client端出场了！client端的sendto系统调用在内核中对应的TCP函数是tcp_sendmsg：

```
	int tcp_sendmsg(struct kiocb *iocb, struct sock *sk, struct msghdr *msg,
			size_t size)
	{
		struct iovec *iov;
		struct tcp_sock *tp = tcp_sk(sk);
		struct sk_buff *skb;
		int iovlen, flags, err, copied = 0;
		int mss_now = 0, size_goal, copied_syn = 0, offset = 0;
		bool sg;
		long timeo;

		lock_sock(sk);

		flags = msg->msg_flags;
		if (flags & MSG_FASTOPEN) {//要使用TFO功能
			err = tcp_sendmsg_fastopen(sk, msg, &copied_syn);//发送TFO数据
			if (err == -EINPROGRESS && copied_syn > 0)
				goto out;
			else if (err)
				goto out_err;
			offset = copied_syn;
		}
```

tcp_sendmsg_fastopen函数用于发送带TFO请求的SYN或携带数据的SYN：

```
	static int tcp_sendmsg_fastopen(struct sock *sk, struct msghdr *msg, int *size)
	{
		struct tcp_sock *tp = tcp_sk(sk);
		int err, flags;

		if (!(sysctl_tcp_fastopen & TFO_CLIENT_ENABLE))
			return -EOPNOTSUPP;
		if (tp->fastopen_req != NULL)
			return -EALREADY; /* Another Fast Open is in progress */

		tp->fastopen_req = kzalloc(sizeof(struct tcp_fastopen_request),
					   sk->sk_allocation);
		if (unlikely(tp->fastopen_req == NULL))
			return -ENOBUFS;
		tp->fastopen_req->data = msg;

		flags = (msg->msg_flags & MSG_DONTWAIT) ? O_NONBLOCK : 0;
		err = __inet_stream_connect(sk->sk_socket, msg->msg_name,
						msg->msg_namelen, flags);      //发送连接请求
		*size = tp->fastopen_req->copied;　//记录发送了多少数据，如果发送的是TFO请求则*size为0
		tcp_free_fastopen_req(tp);
		return err;
	}
```

　　`__inet_stream_connect`函数会调用tcp_connect函数发送SYN：

```
	int tcp_connect(struct sock *sk)
	{
		struct tcp_sock *tp = tcp_sk(sk);
		struct sk_buff *buff;
		int err;
		...
		/* Send off SYN; include data in Fast Open. */
		err = tp->fastopen_req ? tcp_send_syn_data(sk, buff) :
			  tcp_transmit_skb(sk, buff, 1, sk->sk_allocation); //如果使用TFO，则会调用tcp_send_syn_data发送SYN
```
tcp_send_syn_data函数：
```
	static int tcp_send_syn_data(struct sock *sk, struct sk_buff *syn)
	{
		struct tcp_sock *tp = tcp_sk(sk);
		struct tcp_fastopen_request *fo = tp->fastopen_req;
		int syn_loss = 0, space, i, err = 0, iovlen = fo->data->msg_iovlen;
		struct sk_buff *syn_data = NULL, *data;
		unsigned long last_syn_loss = 0;

		tp->rx_opt.mss_clamp = tp->advmss;  /* If MSS is not cached */
		tcp_fastopen_cache_get(sk, &tp->rx_opt.mss_clamp, &fo->cookie,
					   &syn_loss, &last_syn_loss);//查询缓存的TFO cookie信息
		/* Recurring FO SYN losses: revert to regular handshake temporarily */
		if (syn_loss > 1 &&
			time_before(jiffies, last_syn_loss + (60*HZ << syn_loss))) {
			fo->cookie.len = -1;
			goto fallback;
		}

		if (sysctl_tcp_fastopen & TFO_CLIENT_NO_COOKIE)//无论有没有cookie,都发送携带数据的SYN
			fo->cookie.len = -1;
		else if (fo->cookie.len <= 0)      //没有cookie,发送携带TFO请求选项的SYN
			goto fallback;

		/* MSS for SYN-data is based on cached MSS and bounded by PMTU and
		 * user-MSS. Reserve maximum option space for middleboxes that add
		 * private TCP options. The cost is reduced data space in SYN :(
		 */
		if (tp->rx_opt.user_mss && tp->rx_opt.user_mss < tp->rx_opt.mss_clamp)
			tp->rx_opt.mss_clamp = tp->rx_opt.user_mss;
		space = __tcp_mtu_to_mss(sk, inet_csk(sk)->icsk_pmtu_cookie) -
			MAX_TCP_OPTION_SPACE;//计算SYN包中的能够携带的数据的最大大小

		syn_data = skb_copy_expand(syn, skb_headroom(syn), space,
					   sk->sk_allocation);//复制SYN包中的内容，并扩展SKB中的空间
		if (syn_data == NULL)
			goto fallback;

		for (i = 0; i < iovlen && syn_data->len < space; ++i) {//将用户态中缓存的数据copy到内核
			struct iovec *iov = &fo->data->msg_iov[i];
			unsigned char __user *from = iov->iov_base;
			int len = iov->iov_len;

			if (syn_data->len + len > space)//数据总长度大于SKB中空间的总大小
				len = space - syn_data->len;
			else if (i + 1 == iovlen)
				/* No more data pending in inet_wait_for_connect() */
				fo->data = NULL;//数据全部发送完毕，不需要在inet_wait_for_connect中等待时发送

			if (skb_add_data(syn_data, from, len))//将用户数据copy到SKB中
				goto fallback;
		}

		/* Queue a data-only packet after the regular SYN for retransmission */
		data = pskb_copy(syn_data, sk->sk_allocation);
		if (data == NULL)
			goto fallback;
		TCP_SKB_CB(data)->seq++;
		TCP_SKB_CB(data)->tcp_flags &= ~TCPHDR_SYN;
		TCP_SKB_CB(data)->tcp_flags = (TCPHDR_ACK|TCPHDR_PSH);
		tcp_connect_queue_skb(sk, data);
		fo->copied = data->len;

		if (tcp_transmit_skb(sk, syn_data, 0, sk->sk_allocation) == 0) {//发送携带数据的SYN
			tp->syn_data = (fo->copied > 0);
			NET_INC_STATS(sock_net(sk), LINUX_MIB_TCPFASTOPENACTIVE);
			goto done;
		}
		syn_data = NULL;

	fallback:
		/* Send a regular SYN with Fast Open cookie request option */
		if (fo->cookie.len > 0)
			fo->cookie.len = 0;
		err = tcp_transmit_skb(sk, syn, 1, sk->sk_allocation);
		if (err)
			tp->syn_fastopen = 0;
		kfree_skb(syn_data);
	done:
		fo->cookie.len = -1;  /* Exclude Fast Open option for SYN retries */
		return err;
	}
```

如果client是发送TFO请求，则tcp_send_syn_data函数会发送一个不带数据的SYN包，数据部分则会由tcp_sendmsg函数放入发送队列中，等待三次握手完成后再发送。

tcp_transmit_skb函数会调用tcp_syn_options函数构建选项信息，tcp_options_write函数负责将选项写入TCP报头中：

```
	static unsigned int tcp_syn_options(struct sock *sk, struct sk_buff *skb,
					struct tcp_out_options *opts,
					struct tcp_md5sig_key **md5)
	{
		struct tcp_sock *tp = tcp_sk(sk);
		unsigned int remaining = MAX_TCP_OPTION_SPACE;
		struct tcp_fastopen_request *fastopen = tp->fastopen_req;
		...
		if (fastopen && fastopen->cookie.len >= 0) {
			u32 need = TCPOLEN_EXP_FASTOPEN_BASE + fastopen->cookie.len;
			need = (need + 3) & ~3U;  /* Align to 32 bits */
			if (remaining >= need) {
				opts->options |= OPTION_FAST_OPEN_COOKIE;
				opts->fastopen_cookie = &fastopen->cookie;
				remaining -= need;
				tp->syn_fastopen = 1;
			}
		}
```

```
	static void tcp_options_write(__be32 *ptr, struct tcp_sock *tp,
					  struct tcp_out_options *opts)
	{
		u16 options = opts->options;    /* mungable copy */
		...
		if (unlikely(OPTION_FAST_OPEN_COOKIE & options)) {
			struct tcp_fastopen_cookie *foc = opts->fastopen_cookie;

			*ptr++ = htonl((TCPOPT_EXP << 24) |
					   ((TCPOLEN_EXP_FASTOPEN_BASE + foc->len) << 16) |
					   TCPOPT_FASTOPEN_MAGIC);

			memcpy(ptr, foc->val, foc->len);  //如果找到了TFO cookie，则写入；没有RFO cookie则仅仅是一个TFO请求
			if ((foc->len & 3) == 2) {
				u8 *align = ((u8 *)ptr) + foc->len;
				align[0] = align[1] = TCPOPT_NOP;
			}
			ptr += (foc->len + 3) >> 2;
		}
```

client端在每次使用TFO功能时都会在TCP的选项中添加一个TFO选项，与server端进行第一次TFO交互时TFO选项只有4字节长，其值是一个“MAGIC”，这种TFO被称为“TFO请求”；后续的TFO选项长度会增加一个从服务器端获得的TFO cookie的长度值，并且在这个SYN中会携带数据。

server收到SYN后，会在tcp_v4_conn_request中进行处理：

```
	int tcp_v4_conn_request(struct sock *sk, struct sk_buff *skb)
	{
		struct tcp_options_received tmp_opt;
		struct request_sock *req;
		struct inet_request_sock *ireq;
		struct tcp_sock *tp = tcp_sk(sk);
		struct dst_entry *dst = NULL;
		__be32 saddr = ip_hdr(skb)->saddr;
		__be32 daddr = ip_hdr(skb)->daddr;
		__u32 isn = TCP_SKB_CB(skb)->when;
		bool want_cookie = false;
		struct flowi4 fl4;
		struct tcp_fastopen_cookie foc = { .len = -1 };
		struct tcp_fastopen_cookie valid_foc = { .len = -1 };
		struct sk_buff *skb_synack;
		int do_fastopen;
		...
		tcp_parse_options(skb, &tmp_opt, 0, want_cookie ? NULL : &foc);//解析TFO选项
		...
		do_fastopen = tcp_fastopen_check(sk, skb, req, &foc, &valid_foc);//检查TFO选项的合法性
		...
		skb_synack = tcp_make_synack(sk, dst, req,
			fastopen_cookie_present(&valid_foc) ? &valid_foc : NULL);//如果客户端发送的是TFO请求则发送TFO cookie，否则不发送
		...
		if (likely(!do_fastopen)) {
		...
		} else if (tcp_v4_conn_req_fastopen(sk, skb, skb_synack, req))//创建子sock，将SYN中的数据放入socekt中的接收队列中
			goto drop_and_free;

		return 0;
```

tcp_fastopen_check函数用于检查SYN中TFO请求的合法性以及生成TFO cookie：

```
	static bool tcp_fastopen_check(struct sock *sk, struct sk_buff *skb,
					   struct request_sock *req,
					   struct tcp_fastopen_cookie *foc,
					   struct tcp_fastopen_cookie *valid_foc)
	{
		bool skip_cookie = false;
		struct fastopen_queue *fastopenq;

		if (likely(!fastopen_cookie_present(foc))) {//SYN中没有携带TFO选项
			/* See include/net/tcp.h for the meaning of these knobs */
			if ((sysctl_tcp_fastopen & TFO_SERVER_ALWAYS) ||
				((sysctl_tcp_fastopen & TFO_SERVER_COOKIE_NOT_REQD) &&
				(TCP_SKB_CB(skb)->end_seq != TCP_SKB_CB(skb)->seq + 1)))
				skip_cookie = true; /* no cookie to validate */  //无需校验cookie，直接允许SYN中携带数据
			else
				return false;
		}
		fastopenq = inet_csk(sk)->icsk_accept_queue.fastopenq;
		...
		if ((sysctl_tcp_fastopen & TFO_SERVER_ENABLE) == 0 ||
			fastopenq == NULL || fastopenq->max_qlen == 0)//未开启Server端TFO功能
			return false;

		if (fastopenq->qlen >= fastopenq->max_qlen) {//TFO队列已满
			struct request_sock *req1;
			spin_lock(&fastopenq->lock);
			req1 = fastopenq->rskq_rst_head;
			if ((req1 == NULL) || time_after(req1->expires, jiffies)) {
				spin_unlock(&fastopenq->lock);
				NET_INC_STATS_BH(sock_net(sk),
					LINUX_MIB_TCPFASTOPENLISTENOVERFLOW);
				/* Avoid bumping LINUX_MIB_TCPFASTOPENPASSIVEFAIL*/
				foc->len = -1;
				return false;
			}
			fastopenq->rskq_rst_head = req1->dl_next;//替换队列中最老的一个
			fastopenq->qlen--;
			spin_unlock(&fastopenq->lock);
			reqsk_free(req1);
		}
		if (skip_cookie) {//不使用cookie，直接接收数据
			tcp_rsk(req)->rcv_nxt = TCP_SKB_CB(skb)->end_seq;
			return true;
		}
		if (foc->len == TCP_FASTOPEN_COOKIE_SIZE) {//SYN中携带了TFO cookie
			if ((sysctl_tcp_fastopen & TFO_SERVER_COOKIE_NOT_CHKED) == 0) {
				tcp_fastopen_cookie_gen(ip_hdr(skb)->saddr, valid_foc);//生成TFO cookie
				if ((valid_foc->len != TCP_FASTOPEN_COOKIE_SIZE) ||　//TFO初始化不成功
					memcmp(&foc->val[0], &valid_foc->val[0],　//TFO cookie不合法
					TCP_FASTOPEN_COOKIE_SIZE) != 0)
					return false;
				valid_foc->len = -1;
			}
			/* Acknowledge the data received from the peer. */
			tcp_rsk(req)->rcv_nxt = TCP_SKB_CB(skb)->end_seq;
			return true;
		} else if (foc->len == 0) { /* Client requesting a cookie */
			tcp_fastopen_cookie_gen(ip_hdr(skb)->saddr, valid_foc);//生成一个TFO cookie保存在valid_foc中
			NET_INC_STATS_BH(sock_net(sk),
				LINUX_MIB_TCPFASTOPENCOOKIEREQD);
		} else {
			/* Client sent a cookie with wrong size. Treat it
			 * the same as invalid and return a valid one.
			 */
			tcp_fastopen_cookie_gen(ip_hdr(skb)->saddr, valid_foc);
		}
		return false;
	}
```

1327：rskq_rst_head为NULL的场景为有很多带TFO的SYN到来但SYN|ACK发送后并没有收到RST包，这意味着之前收到的那些带数据的TFO SYN可能是合法的；如果不为NULL但对立中最老的一个仍然没有超时的话，也不能将其替换

1344-1351：如果clienet端的TFO不是请求，而是cookie，则不设置valid_foc；另外如果server端被设置为不检查cookie的合法性，则生成一个cookie再检查SYN中的TFO cookie的合法性，如果不合法则不使用TFO功能。

tcp_make_synack函数会将tcp_fastopen_check中生成的TFO cookie写入TCP首部中，tcp_synack_options函数用来构建SYN|ACK报文的选项信息：

```
	static unsigned int tcp_synack_options(struct sock *sk,
					   struct request_sock *req,
					   unsigned int mss, struct sk_buff *skb,
					   struct tcp_out_options *opts,
					   struct tcp_md5sig_key **md5,
					   struct tcp_fastopen_cookie *foc)
	{
		...
		if (foc != NULL) {
			u32 need = TCPOLEN_EXP_FASTOPEN_BASE + foc->len;
			need = (need + 3) & ~3U;  /* Align to 32 bits */
			if (remaining >= need) {
				opts->options |= OPTION_FAST_OPEN_COOKIE;
				opts->fastopen_cookie = foc;
				remaining -= need;
			}
		}
		...
```

将选项信息写入SYN|ACK的方法与client发送SYN时一样，都是调用tcp_options_write函数。可以看出，TCP server端会返回给发送TFO请求的client端一个TFO cookie。client发送的下一个带数据的SYN必须携带这个cookie，而TCP server对这样的SYN回复的SYN|ACK中不会携带TFO选项。

在SYN携带TFO cookie的情况下TCP server会在收到SYN时就创建sock，这个功能由cp_v4_conn_req_fastopen函数完成：
```
	static int tcp_v4_conn_req_fastopen(struct sock *sk,
						struct sk_buff *skb,
						struct sk_buff *skb_synack,
						struct request_sock *req)
	{
		struct tcp_sock *tp = tcp_sk(sk);
		struct request_sock_queue *queue = &inet_csk(sk)->icsk_accept_queue;
		const struct inet_request_sock *ireq = inet_rsk(req);
		struct sock *child;
		...

		child = inet_csk(sk)->icsk_af_ops->syn_recv_sock(sk, skb, req, NULL);//生成子socket，其状态为TCP_SYN_RECV
		...
		err = ip_build_and_send_pkt(skb_synack, sk, ireq->loc_addr,
						ireq->rmt_addr, ireq->opt);//构建SYN|ACK的IP头并将其发送出去
		err = net_xmit_eval(err);
		if (!err)
			tcp_rsk(req)->snt_synack = tcp_time_stamp;
		/* XXX (TFO) - is it ok to ignore error and continue? */

		spin_lock(&queue->fastopenq->lock);
		queue->fastopenq->qlen++;//将这个连接计入TFO queue
		spin_unlock(&queue->fastopenq->lock);
		...
		tp = tcp_sk(child);

		tp->fastopen_rsk = req;
		/* Do a hold on the listner sk so that if the listener is being
		 * closed, the child that has been accepted can live on and still
		 * access listen_lock.
		 */
		sock_hold(sk);
		tcp_rsk(req)->listener = sk;

		/* RFC1323: The window in SYN & SYN/ACK segments is never
		 * scaled. So correct it appropriately.
		 */
		tp->snd_wnd = ntohs(tcp_hdr(skb)->window);

		/* Activate the retrans timer so that SYNACK can be retransmitted.
		 * The request socket is not added to the SYN table of the parent
		 * because it's been added to the accept queue directly.
		 */
		inet_csk_reset_xmit_timer(child, ICSK_TIME_RETRANS,
			TCP_TIMEOUT_INIT, TCP_RTO_MAX);

		/* Add the child socket directly into the accept queue */
		inet_csk_reqsk_queue_add(sk, req, child);

		/* Now finish processing the fastopen child socket. */
		inet_csk(child)->icsk_af_ops->rebuild_header(child);
		tcp_init_congestion_control(child);
		tcp_mtup_init(child);
		tcp_init_buffer_space(child);
		tcp_init_metrics(child);

		/* Queue the data carried in the SYN packet. We need to first
		 * bump skb's refcnt because the caller will attempt to free it.
		 *
		 * XXX (TFO) - we honor a zero-payload TFO request for now.
		 * (Any reason not to?)
		 */
		if (TCP_SKB_CB(skb)->end_seq == TCP_SKB_CB(skb)->seq + 1) {//SYN包中没有数据
			/* Don't queue the skb if there is no payload in SYN.
			 * XXX (TFO) - How about SYN+FIN?
			 */
			tp->rcv_nxt = TCP_SKB_CB(skb)->end_seq;
		} else {
			skb = skb_get(skb);
			skb_dst_drop(skb);
			__skb_pull(skb, tcp_hdr(skb)->doff * 4);
			skb_set_owner_r(skb, child);
			__skb_queue_tail(&child->sk_receive_queue, skb);//将数据放入child的接收队列中
			tp->rcv_nxt = TCP_SKB_CB(skb)->end_seq;
			tp->syn_data_acked = 1;
		}
		sk->sk_data_ready(sk, 0);//通知持有listening socket的进程调用accept系统调用创建新连接
		bh_unlock_sock(child);
		sock_put(child);
		WARN_ON(req->sk == NULL);
		return 0;
	}
```

应用进程收到listening socket的可读通告后，使用accept系统调用建立socket，就可以立即从这个新的socket中读到数据，并开始与客户端进行数据交互。

如果client的TFO是cookie，则SYN|ACK的处理过程与不使用TFO的情况是一样的；如果client发送的TFO是请求，则在收到SYN|ACK时需要将包中的TFO cookie保存下来：

```
	static int tcp_rcv_synsent_state_process(struct sock *sk, struct sk_buff *skb,
						 const struct tcphdr *th, unsigned int len)
	{
		struct inet_connection_sock *icsk = inet_csk(sk);
		struct tcp_sock *tp = tcp_sk(sk);
		struct tcp_fastopen_cookie foc = { .len = -1 };
		int saved_clamp = tp->rx_opt.mss_clamp;

		tcp_parse_options(skb, &tp->rx_opt, 0, &foc);//解析TFO选项
		...
			if ((tp->syn_fastopen || tp->syn_data) && //如果发送过TFO选项或在SYN中发送过数据
				tcp_rcv_fastopen_synack(sk, skb, &foc))//记录SYN｜ACK中的FTO cookie
				return -1;
```

tcp_rcv_fastopen_synack函数检查并保存server端发送的TFO cookie：

```
	static bool tcp_rcv_fastopen_synack(struct sock *sk, struct sk_buff *synack,
						struct tcp_fastopen_cookie *cookie)
	{
		struct tcp_sock *tp = tcp_sk(sk);
		struct sk_buff *data = tp->syn_data ? tcp_write_queue_head(sk) : NULL;
		u16 mss = tp->rx_opt.mss_clamp;
		bool syn_drop;

		if (mss == tp->rx_opt.user_mss) {
			struct tcp_options_received opt;

			/* Get original SYNACK MSS value if user MSS sets mss_clamp */
			tcp_clear_options(&opt);
			opt.user_mss = opt.mss_clamp = 0;
			tcp_parse_options(synack, &opt, 0, NULL);
			mss = opt.mss_clamp;
		}

		if (!tp->syn_fastopen)  /* Ignore an unsolicited cookie */
			cookie->len = -1;//如果客户端没有发送TFO请求但服务器给出了TFO cookie，忽略之

		/* The SYN-ACK neither has cookie nor acknowledges the data. Presumably
		 * the remote receives only the retransmitted (regular) SYNs: either
		 * the original SYN-data or the corresponding SYN-ACK is lost.
		 */
		syn_drop = (cookie->len <= 0 && data && tp->total_retrans); //客户端认为发生了SYN丢失事件

		tcp_fastopen_cache_set(sk, mss, cookie, syn_drop);//存储SYN｜ACK包中的TFO cookie，并记录发现SYN丢失事件的时间

		if (data) { /* Retransmit unacked data in SYN */
			tcp_for_write_queue_from(data, sk) {
				if (data == tcp_send_head(sk) ||
					__tcp_retransmit_skb(sk, data))
					break;
			}
			tcp_rearm_rto(sk);
			return true;
		}
		tp->syn_data_acked = tp->syn_data;
		return false;
	}
```

在保存了TFO cookie后，client在向相同IP地址的server发送SYN时都可以携带数据（这时必须发送TFO cookie）。client在收到SYN|ACK后需要回复ACK报文，服务器端在接收ACK时对TFO的处理如下：

```
	int tcp_rcv_state_process(struct sock *sk, struct sk_buff *skb,
				  const struct tcphdr *th, unsigned int len)
	{
		struct tcp_sock *tp = tcp_sk(sk);
		struct inet_connection_sock *icsk = inet_csk(sk);
		struct request_sock *req;
		...
		req = tp->fastopen_rsk;//找到在SYN请求到来后创建子socket时使用的request sock
		if (req != NULL) {
			WARN_ON_ONCE(sk->sk_state != TCP_SYN_RECV &&
				sk->sk_state != TCP_FIN_WAIT1);

			if (tcp_check_req(sk, skb, req, NULL, true) == NULL)//检查包的合法性
				goto discard;
		}
		...
			switch (sk->sk_state) {
			case TCP_SYN_RECV:
				if (acceptable) {
					/* Once we leave TCP_SYN_RECV, we no longer
					 * need req so release it.
					 */
					if (req) {//使用了TFO cookie
						tcp_synack_rtt_meas(sk, req);
						tp->total_retrans = req->num_retrans;

						reqsk_fastopen_remove(sk, req, false);//将request sock从TFO queue中删除，TFO流程全部结束
					} else {
		...
```

综上，TFO在收到SYN的时候就创建socket并将数据提交给应用进程，这样就比普通模式节省了SYN|ACK与ACK的交互时间，减小了通信延迟。

