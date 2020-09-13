---
layout: post
title: "TCP_NEW_SYN_RECV"
date: 2020-09-10 04:40:00 +0800
comments: false
categories:
- 2020
- 2020~09
- kernel
- kernel~net
tags:
---

https://git.kernel.org/pub/scm/linux/kernel/git/stable/linux.git/commit/?id=10feb428a5045d5eb18a5d755fbb8f0cc9645626

https://git.kernel.org/pub/scm/linux/kernel/git/stable/linux.git/commit/?id=d34ac51b76e8c7de6094cfb11780ef9c2b93469f

https://git.kernel.org/pub/scm/linux/kernel/git/stable/linux.git/commit/?id=4e9a578e5b6bdfa8b7fed7a41f28a86a7cffc85f

https://git.kernel.org/pub/scm/linux/kernel/git/stable/linux.git/commit/?id=079096f103faca2dd87342cca6f23d4b34da8871

https://git.kernel.org/pub/scm/linux/kernel/git/stable/linux.git/commit/?id=2215089b224412bfb28c5ae823b2a5d4e28a49d7

https://git.kernel.org/pub/scm/linux/kernel/git/stable/linux.git/commit/?id=26e3736090e1037ac929787df21c05497479b77f

https://git.kernel.org/pub/scm/linux/kernel/git/stable/linux.git/commit/?id=85645bab57bfc6b0b43bb96a301c4ef83925c07d

https://git.kernel.org/pub/scm/linux/kernel/git/stable/linux.git/commit/?id=a9407000038805e5215a49c0a50c9e2b2ff38220

https://git.kernel.org/pub/scm/linux/kernel/git/stable/linux.git/commit/?id=8b5801477926a2b018afc84a53c0b8818843fe73

https://git.kernel.org/pub/scm/linux/kernel/git/stable/linux.git/commit/?id=a8399231f0b6e72bc140bcc4fecb0c622298a6bd

https://git.kernel.org/pub/scm/linux/kernel/git/stable/linux.git/commit/?id=caf3f2676aaad395903d24a54e22f8ac4bc4823d

https://git.kernel.org/pub/scm/linux/kernel/git/stable/linux.git/commit/?id=4bdc3d66147b3a623b32216a45431d0cff005f50

https://git.kernel.org/pub/scm/linux/kernel/git/stable/linux.git/commit/?id=c2f34a65a61cd1ace3b53c93e8b38d2f79f4ff0d

https://git.kernel.org/pub/scm/linux/kernel/git/stable/linux.git/commit/?id=f03f2e154f52fdaa982de7e2c386737679963dc9

https://git.kernel.org/pub/scm/linux/kernel/git/stable/linux.git/commit/?id=fff1f3001cc58b5064a0f1154a7ac09b76f29c44

https://git.kernel.org/pub/scm/linux/kernel/git/stable/linux.git/commit/?id=aac065c50aba0c534a929aeb687eb68c58e523b8

结合以上patch，在 [kernel-3.10.0-693.11.1.el7.src.rpm](http://buildlogs.cdn.centos.org/c7.1708.u/kernel/20171204203818/3.10.0-693.11.1.el7.x86_64/kernel-3.10.0-693.11.1.el7.x86_64.rpm) 内核上引入 TCP_NEW_SYN_RECV patch

好处：mptcp和4.15.0的基本一样。

不再需要spin_lock(listen_sk)，最大的互斥变成atomic。（去除atomic看 [tcp连接查找](/blog/2018/09/28/kernel-sk_lookup/) ）

#### ipv6_addr_v4mapped

sk_ehashfn 被 ipv4, ipv6 共用，req hash 的时候可用的变量不多，用的是 ipv6_addr_v4mapped(&sk->sk_v6_daddr) 判断是否mapped，所以原先的sk->sk_daddr = addr; sk->sk_rcv_saddr = addr; 换成下面两个函数。
```
	static inline void sk_daddr_set(struct sock *sk, __be32 addr)
	{
		sk->sk_daddr = addr; /* alias of inet_daddr */
	#if IS_ENABLED(CONFIG_IPV6)
		ipv6_addr_set_v4mapped(addr, &sk->sk_v6_daddr);
	#endif
	}

	static inline void sk_rcv_saddr_set(struct sock *sk, __be32 addr)
	{
		sk->sk_rcv_saddr = addr; /* alias of inet_rcv_saddr */
	#if IS_ENABLED(CONFIG_IPV6)
		ipv6_addr_set_v4mapped(addr, &sk->sk_v6_rcv_saddr);
	#endif
	}
```

#### ir_iif, ireq_net, ireq_state

ir_iif, ireq_net, ireq_state 需要在 req 创建时赋值，因为插入ehash表后的查找需要用到这些变量。

#### reqsk_put

原先部分reqsk_free需要换成reqsk_put，因为req已经和sk一样，靠自己的refcnt维护

#### backlog

```
	if (sk->sk_state == TCP_LISTEN) {
		ret = tcp_v4_do_rcv(sk, skb);
		goto put_and_return;
	}
```

listen_sk 的包要在tcp_v4_rcv里处理完，不能再加入listen_sk的backlog处理，因为req已经不在listen_sk->icsk_accept_queue.listen_opt.syn_table里，而backlog(=tcp_v4_do_rcv)又不会再lookup_sk，导致无法找到req。


原来的处理是：按listen_sk的收到包的顺序处理，并且需要spin_lock。按照下面的顺序，即使syn、ack、GET包都在backlog里也能处理(GET包查不到req，能查到establish)。TCP_NEW_SYN_RECV 主要是优化调spin_lock(listen_sk)

```
	tcp_v4_hnd_req() {
		req = inet_csk_search_req()

		if (req)
			return tcp_check_req()

		nsk = inet_lookup_established()
		if (nsk && nsk->sk_state != TCP_TIME_WAIT)
			return nsk;
	}
```

