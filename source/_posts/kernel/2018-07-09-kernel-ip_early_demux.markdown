---
layout: post
title: "参数ip_early_demux"
date: 2018-07-09 01:12:00 +0800
comments: false
categories:
- 2018
- 2018~07
- kernel
- kernel~net
tags:
---

ip_early_demux 内核中默认是1（开启），
所以在ip_rcv_finish 到 tcp_v4_rcv 中 skb->destructor = sock_edemux;

且很大概率 skb->_skb_refdst = (unsigned long)dst | SKB_DST_NOREF; // #define SKB_DST_NOREF   1UL


对于NOREF的dst，如果要缓存(tcp_prequeue()或sk_add_backlog()), 则要调skb_dst_force(skb);
```
	static inline void skb_dst_force(struct sk_buff *skb)
	{
		if (skb_dst_is_noref(skb)) {
			WARN_ON(!rcu_read_lock_held());
			skb->_skb_refdst &= ~SKB_DST_NOREF;
			dst_clone(skb_dst(skb));
		}   
	}
```


--------------------------------

http://blog.chinaunix.net/uid-20662820-id-4935075.html

The routing cache has been suppressed in Linux 3.6 after a 2 years effort by David and the other Linux kernel developers. 
The global cache has been suppressed and some stored information have been moved to more separate resources like socket.

Metrics were stored in the routing cache entry which has disappeared. So it has been necessary to introduce a separate TCP metrics cache. 
A netlink interface is available to update/delete/add entry to the cache.

总结起来说就是Linux内核从3.6开始将全局的route cache全部剔除，取而代之的是各个子系统（tcp协议栈）内部的cache，由各个子系统维护。

当内核接收到一个TCP数据包来说，首先需要查找skb对应的路由，然后查找skb对应的socket。David Miller 发现这样做是一种浪费，对于属于同一个socket(只考虑ESTABLISHED情况）的路由是相同的，那么如果能将skb的路由缓存到socket（skb->sk)中，就可以只查找查找一次skb所属的socket，就可以顺便把路由找到了，于是David Miller提交了一个patch ipv4: Early TCP socket demux

```
	(commit 41063e9dd11956f2d285e12e4342e1d232ba0ea2)
	ipv4: Early TCP socket demux.

		Input packet processing for local sockets involves two major demuxes.
		One for the route and one for the socket.

		But we can optimize this down to one demux for certain kinds of local
		sockets.

		Currently we only do this for established TCP sockets, but it could
		at least in theory be expanded to other kinds of connections.

		If a TCP socket is established then it's identity is fully specified.

		This means that whatever input route was used during the three-way
		handshake must work equally well for the rest of the connection since
		the keys will not change.

		Once we move to established state, we cache the receive packet's input
		route to use later.

		Like the existing cached route in sk->sk_dst_cache used for output
		packets, we have to check for route invalidations using dst->obsolete
		and dst->ops->check().

		Early demux occurs outside of a socket locked section, so when a route
		invalidation occurs we defer the fixup of sk->sk_rx_dst until we are
		actually inside of established state packet processing and thus have
		the socket locked.
```

然而Davem添加的这个patch是有局限的，因为这个处理对于转发的数据包，增加了一个在查找路由之前查找socket的逻辑，可能导致转发效率的降低。
Alexander Duyck提出增加一个ip_early_demux参数来控制是否启动这个特性。

```
	This change is meant to add a control for disabling early socket demux.
	The main motivation behind this patch is to provide an option to disable
	the feature as it adds an additional cost to routing that reduces overall
	throughput by up to 5%. For example one of my systems went from 12.1Mpps
	to 11.6 after the early socket demux was added. It looks like the reason
	for the regression is that we are now having to perform two lookups, first
	the one for an established socket, and then the one for the routing table.

	By adding this patch and toggling the value for ip_early_demux to 0 I am
	able to get back to the 12.1Mpps I was previously seeing.
```

```
	static int ip_rcv_finish(struct sk_buff *skb)
	{
		const struct iphdr *iph = ip_hdr(skb);
		struct rtable *rt;

		if (sysctl_ip_early_demux && !skb_dst(skb) && skb->sk == NULL) {
			const struct net_protocol *ipprot;
			int protocol = iph->protocol;

			ipprot = rcu_dereference(inet_protos[protocol]);
			if (ipprot && ipprot->early_demux) {
				ipprot->early_demux(skb);
				/* must reload iph, skb->head might have changed */
				iph = ip_hdr(skb);
			}
		}

		/*
		 * Initialise the virtual path cache for the packet. It describes
		 * how the packet travels inside Linux networking.
		 */
		if (!skb_dst(skb)) {
			int err = ip_route_input_noref(skb, iph->daddr, iph->saddr,
							   iph->tos, skb->dev);
			if (unlikely(err)) {
				if (err == -EXDEV)
					NET_INC_STATS_BH(dev_net(skb->dev),
						 LINUX_MIB_IPRPFILTER);
				goto drop;
			}
		}
		......
```
ip_early_demux就这样诞生了，目前内核中默认是1（开启），但是如果你的数据流量中60%以上都是转发的，那么请关闭这个特性。

```
	void tcp_v4_early_demux(struct sk_buff *skb)
	{
		const struct iphdr *iph;
		const struct tcphdr *th;
		struct sock *sk;

		if (skb->pkt_type != PACKET_HOST)
			return;

		if (!pskb_may_pull(skb, skb_transport_offset(skb) + sizeof(struct tcphdr)))
			return;

		iph = ip_hdr(skb);
		th = tcp_hdr(skb);

		if (th->doff < sizeof(struct tcphdr) / 4)
			return;

		sk = __inet_lookup_established(dev_net(skb->dev), &tcp_hashinfo,
					       iph->saddr, th->source,
					       iph->daddr, ntohs(th->dest),
					       skb->skb_iif);
		if (sk) {
			skb->sk = sk;
			skb->destructor = sock_edemux;
			if (sk->sk_state != TCP_TIME_WAIT) {
				struct dst_entry *dst = sk->sk_rx_dst;

				if (dst)
					dst = dst_check(dst, 0);
				if (dst &&
				    inet_sk(sk)->rx_dst_ifindex == skb->skb_iif)
					skb_dst_set_noref(skb, dst);
			}
		}
	}
```


