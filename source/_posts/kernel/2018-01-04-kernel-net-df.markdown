---
layout: post
title: "本地IP包分片--local_df,ignore_df"
date: 2018-01-04 01:41:00 +0800
comments: false
categories:
- 2018
- 2018~01
- kernel
- kernel~net
tags:
---

local_df 和 ignore_df 是一个意思，在某个版本rename了

#### ip_queue_xmit 函数中有：
```
	if (ip_dont_fragment(sk, &rt->dst) && !skb->ignore_df)
		iph->frag_off = htons(IP_DF);
	else
		iph->frag_off = 0;
```

ip_dont_fragment
```
	static inline
	int ip_dont_fragment(struct sock *sk, struct dst_entry *dst)
	{
		return  inet_sk(sk)->pmtudisc == IP_PMTUDISC_DO ||
			(inet_sk(sk)->pmtudisc == IP_PMTUDISC_WANT &&
			 !(dst_metric_locked(dst, RTAX_MTU)));
	}
```

一般情况下都是开启pmtu、skb->ignore_df = 0, 所以 iph->frag_off = htons(IP_DF);

#### ip_queue_xmit -> ip_finish_output -> ip_fragment :

```
	static int ip_fragment(struct sock *sk, struct sk_buff *skb,
		       unsigned int mtu, 
		       int (*output)(struct sock *, struct sk_buff *))
	{
		struct iphdr *iph = ip_hdr(skb);

		// 如果需要分片，直接进入分片函数
		if ((iph->frag_off & htons(IP_DF)) == 0)
			return ip_do_fragment(sk, skb, output);

		// 如果没设置分片，或手动设置的分片过大，则直接丢弃
		if (unlikely(!skb->ignore_df ||
			     (IPCB(skb)->frag_max_size &&
			      IPCB(skb)->frag_max_size > mtu))) {
			struct rtable *rt = skb_rtable(skb);
			struct net_device *dev = rt->dst.dev;

			IP_INC_STATS(dev_net(dev), IPSTATS_MIB_FRAGFAILS);
			icmp_send(skb, ICMP_DEST_UNREACH, ICMP_FRAG_NEEDED,
				  htonl(mtu));
			kfree_skb(skb);
			return -EMSGSIZE;
		}

		// 所以设置 skb->ignore_df = 1 且 skb->len > mtu 则执行到这里
		return ip_do_fragment(sk, skb, output);
	}
```

所以设置 skb->ignore_df = 1 且 skb->len > mtu 则执行 ip_do_fragment 进行IP分片,

分片是按网卡mtu进行，如果mss小于网卡mtu-40，则需要设置 IPCB(skb)->frag_max_size


#### iph->frag_off 定义
```
	#define IP_DF           0x4000          /* Flag: "Don't Fragment"       */
	#define IP_MF           0x2000          /* Flag: "More Fragments"       */
	#define IP_OFFSET       0x1FFF          /* "Fragment Offset" part       */
```

1) 不分片的包 iph->frag_off = htons(IP_DF)  
2) 最后一个分片包 ((ntohs(iph->frag_off) & IP_OFFSET) > 0)  
3) 其余分片包 ((ntohs(iph->frag_off) & IP_OFFSET) > 0 && (iph->frag_off & htons(IP_MF)) > 0)  

