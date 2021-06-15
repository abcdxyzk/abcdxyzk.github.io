---
layout: post
title: "Linux2.6下ESP包解析流程"
date: 2021-06-15 21:50:00 +0800
comments: false
categories:
- 2021
- 2021~06
- kernel
- kernel~ipsec
tags:
---

http://www.360doc.com/content/11/0516/05/706976_117227003.shtml

以下Linux内核代码版本为2.6.19.2。

### 2. 流程分析

#### 2.1 esp协议结构

esp协议结构定义，对于每个IPv4上层的协议，如TCP、UDP、ICMP、IGMP、ESP、AH等都需要定义这个
结构挂接到IPv4的协议链表中，当接收到IP数据包时，会根据包中定义的IP协议号找到该结构，然后
调用其成员handler函数进行处理。

```
	/* net/ipv4/esp4.c */
	static struct net_protocol esp4_protocol = {
		.handler = xfrm4_rcv,
		.err_handler = esp4_err,
		.no_policy = 1,
	};
```

esp协议的handler函数是xfrm4_rcv()

2.2 xfrm4_rcv
```
	/* net/ipv4/xfrm4_input.c */
	int xfrm4_rcv(struct sk_buff *skb)
	{
		return xfrm4_rcv_encap(skb, 0);
	}
```

实际就是xfrm4_rcv_encap，封装类型参数设置为0，即没封装数据

2.3 xfrm4_rcv_encap

```
	/* net/ipv4/xfrm4_input.c */
	int xfrm4_rcv_encap(struct sk_buff *skb, __u16 encap_type)
	{
		int err;
		__be32 spi, seq;
		struct xfrm_state *xfrm_vec[XFRM_MAX_DEPTH];
		struct xfrm_state *x;
		int xfrm_nr = 0;
		int decaps = 0;
		// 获取skb中的spi和序列号信息
		if ((err = xfrm4_parse_spi(skb, skb->nh.iph->protocol, &spi, &seq)) != 0)
			goto drop;
		// 进入循环进行解包操作
		do {
			struct iphdr *iph = skb->nh.iph;
			// 循环解包次数太深的话放弃
			if (xfrm_nr == XFRM_MAX_DEPTH)
				goto drop;
			// 根据地址, SPI和协议查找SA
			x = xfrm_state_lookup((xfrm_address_t *)&iph->daddr, spi, iph->protocol, AF_INET);
			if (x == NULL)
				goto drop;
			// 以下根据SA定义的操作对数据解码
			spin_lock(&x->lock);
			if (unlikely(x->km.state != XFRM_STATE_VALID))
				goto drop_unlock;
			// 检查由SA指定的封装类型是否和函数指定的封装类型相同
			if ((x->encap ? x->encap->encap_type : 0) != encap_type)
				goto drop_unlock;
			// SA重放窗口检查
			if (x->props.replay_window && xfrm_replay_check(x, seq))
				goto drop_unlock;
			// SA生存期检查
			if (xfrm_state_check_expire(x))
				goto drop_unlock;
			// type可为esp,ah,ipcomp, ipip等, 对输入数据解密
			if (x->type->input(x, skb))
				goto drop_unlock;
			/* only the first xfrm gets the encap type */
			encap_type = 0;
			// 更新重放窗口
			if (x->props.replay_window)
				xfrm_replay_advance(x, seq);
			// 包数,字节数统计
			x->curlft.bytes += skb->len;
			x->curlft.packets++;
			spin_unlock(&x->lock);
			xfrm_vec[xfrm_nr++] = x;
			// mode可为通道,传输等模式, 对输入数据解封装
			if (x->mode->input(x, skb))
				goto drop;
			// 如果是IPSEC通道模式，将decaps参数置1，否则表示是传输模式
			if (x->props.mode == XFRM_MODE_TUNNEL) {
				decaps = 1;
				break;
			}
			// 看内层协议是否还要继续解包, 不需要解时返回1, 需要解时返回0, 错误返回负数
			// 协议类型可以多层封装的,比如用AH封装ESP, 就得先解完AH再解ESP
			if ((err = xfrm_parse_spi(skb, skb->nh.iph->protocol, &spi, &seq)) < 0)
				goto drop;
		} while (!err);
		/* Allocate new secpath or COW existing one. */
		// 为skb包建立新的安全路径(struct sec_path)
		if (!skb->sp || atomic_read(&skb->sp->refcnt) != 1) {
			struct sec_path *sp;
			sp = secpath_dup(skb->sp);
			if (!sp)
				goto drop;
			if (skb->sp)
				secpath_put(skb->sp);
			skb->sp = sp;
		}
		if (xfrm_nr + skb->sp->len > XFRM_MAX_DEPTH)
			goto drop;
		// 将刚才循环解包用到的SA拷贝到安全路径
		// 因此检查一个数据包是否是普通明文包还是解密后的明文包就看skb->sp参数是否为空
		memcpy(skb->sp->xvec + skb->sp->len, xfrm_vec,
									xfrm_nr * sizeof(xfrm_vec[0]));
		skb->sp->len += xfrm_nr;
		nf_reset(skb);
		if (decaps) {
			// 通道模式
			if (!(skb->dev->flags&IFF_LOOPBACK)) {
				dst_release(skb->dst);
				skb->dst = NULL;
			}
			// 重新进入网卡接收函数
			netif_rx(skb);
			return 0;
		} else {
			// 传输模式
	#ifdef CONFIG_NETFILTER
			// 如果定义NETFILTER, 进入PRE_ROUTING链处理,然后进入路由选择处理
			// 其实现在已经处于INPUT点, 但解码后需要将该包作为一个新包看待
			// 可能需要进行目的NAT操作, 这时候可能目的地址就会改变不是到自身
			// 的了, 因此需要将其相当于是放回PRE_PROUTING点去操作, 重新找路由
			// 这也说明可以制定针对解码后明文包的NAT规则,在还是加密包的时候不匹配
			// 但解码后能匹配上
			__skb_push(skb, skb->data - skb->nh.raw);
			skb->nh.iph->tot_len = htons(skb->len);
			ip_send_check(skb->nh.iph);
			NF_HOOK(PF_INET, NF_IP_PRE_ROUTING, skb, skb->dev, NULL, xfrm4_rcv_encap_finish);
			return 0;
	#else
			// 内核不支持NETFILTER, 该包肯定就是到自身的了
			// 返回IP协议的负值, 表示重新进行IP层协议的处理
			// 用解码后的内层协议来处理数据
			return -skb->nh.iph->protocol;
	#endif
		}
	drop_unlock:
		spin_unlock(&x->lock);
		xfrm_state_put(x);
	drop:
		while (--xfrm_nr >= 0)
			xfrm_state_put(xfrm_vec[xfrm_nr]);
		kfree_skb(skb);
		return 0;
	}

```

最后说一下返回负协议值的处理, IP上层协议的handler是在ip_local_deliver_finish()函数中调用的:

```
	/* net/ipv4/ip_input.c */
	static inline int ip_local_deliver_finish(struct sk_buff *skb)
	{
		int ihl = skb->nh.iph->ihl*4;
		__skb_pull(skb, ihl);
		/* Point into the IP datagram, just past the header. */
		skb->h.raw = skb->data;
		rcu_read_lock();
		{
			/* Note: See raw.c and net/raw.h, RAWV4_HTABLE_SIZE==MAX_INET_PROTOS */
			int protocol = skb->nh.iph->protocol;
			int hash;
			struct sock *raw_sk;
			struct net_protocol *ipprot;
		resubmit:
			// 协议hash值, IPv4最大支持255种协议
			hash = protocol & (MAX_INET_PROTOS - 1);
			raw_sk = sk_head(&raw_v4_htable[hash]);
			/* If there maybe a raw socket we must check - if not we
				* don't care less
				*/
			if (raw_sk && !raw_v4_input(skb, skb->nh.iph, hash))
				raw_sk = NULL;
			// 直接在协议数组中获取协议指针
			if ((ipprot = rcu_dereference(inet_protos[hash])) != NULL) {
				int ret;
				if (!ipprot->no_policy) {
					if (!xfrm4_policy_check(NULL, XFRM_POLICY_IN, skb)) {
						kfree_skb(skb);
						goto out;
					}
					nf_reset(skb);
				}
				// 调用协议handler
				ret = ipprot->handler(skb);
				if (ret < 0) {
					// 如果返回值为负, 取反后重新跳回resubmit点进行选协议处理
					protocol = -ret;
					goto resubmit;
				}
				IP_INC_STATS_BH(IPSTATS_MIB_INDELIVERS);
			} else {
				if (!raw_sk) {
					if (xfrm4_policy_check(NULL, XFRM_POLICY_IN, skb)) {
						IP_INC_STATS_BH(IPSTATS_MIB_INUNKNOWNPROTOS);
						icmp_send(skb, ICMP_DEST_UNREACH,
									ICMP_PROT_UNREACH, 0);
					}
				} else
					IP_INC_STATS_BH(IPSTATS_MIB_INDELIVERS);
				kfree_skb(skb);
			}
		}
		out:
		rcu_read_unlock();
		return 0;
	}
```

