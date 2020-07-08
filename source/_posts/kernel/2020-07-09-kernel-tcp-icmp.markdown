---
layout: post
title: "TCP包增一个ICMP头"
date: 2020-07-09 02:00:00 +0800
comments: false
categories:
- 2020
- 2020~07
- kernel
- kernel~net
tags:
---

发送加头，接收解头。checksum失效，需要额外处理

```
	#include <linux/module.h>
	#include <linux/kernel.h>
	#include <linux/ip.h>
	#include <linux/tcp.h>
	#include <linux/sctp.h>
	#include <linux/icmp.h>
	#include <linux/slab.h>
	
	#include <net/ip.h>
	#include <net/tcp.h>
	#include <net/udp.h>
	#include <net/icmp.h>                   /* for icmp_send */
	#include <net/route.h>
	#include <net/ip6_checksum.h>
	#include <net/netns/generic.h>		/* net_generic() */
	
	#include <linux/netfilter.h>
	#include <linux/netfilter_ipv4.h>
	
	#ifdef CONFIG_IP_VS_IPV6
	#include <net/ipv6.h>
	#include <linux/netfilter_ipv6.h>
	#include <net/ip6_route.h>
	#endif
	
	#include <net/ip_vs.h>
	#include <linux/dns_resolver.h>
	
	
	
	#include <linux/module.h>
	#include <linux/kernel.h>
	#include <linux/version.h>
	#include <linux/ip.h>
	#include <linux/tcp.h>
	#include <linux/icmp.h>
	#include <linux/if_arp.h>
	#include <linux/if_ether.h>
	#include <linux/kallsyms.h>
	#include <linux/inetdevice.h>
	#include <linux/netdevice.h>
	#include <net/ip.h>
	#include <net/tcp.h>
	#include <net/udp.h>
	#include <net/icmp.h>
	#include <net/arp.h>
	#include <net/route.h>
	#include <net/neighbour.h>
	#include <net/netevent.h>
	#if (LINUX_VERSION_CODE >= KERNEL_VERSION(2, 6, 32))
	#include <net/net_namespace.h>
	#endif
	#include <linux/netfilter.h>
	#include <linux/netfilter_ipv4.h>
	
	#include <linux/inet.h>
	#include <linux/skbuff.h>
	#include <linux/kthread.h>
	
	int icmp_port = 80;
	module_param(icmp_port, int, 0644);
	
	struct addhdr {
		u32 saddr, daddr;
		u16 sport, dport;
		u16 len;
		u16 magic;
	};
	
	static unsigned int local_out(void *priv, struct sk_buff *skb, const struct nf_hook_state *state)
	{
		struct iphdr *iph = ip_hdr(skb);
		struct tcphdr *th;
		struct icmphdr *icmp;
		struct addhdr *add;
		int delta;
	
		iph = ip_hdr(skb);
		th = tcp_hdr(skb);
		if (iph->protocol != IPPROTO_TCP)
			return NF_ACCEPT;
	
		if (ntohs(th->source) != icmp_port && ntohs(th->dest) != icmp_port)
			return NF_ACCEPT;
	
		if (skb->len + sizeof(struct icmphdr) + sizeof(struct addhdr) > 1500)
			return NF_ACCEPT;
	
		delta = sizeof(struct icmphdr) + sizeof(struct addhdr) + sizeof(struct ethhdr) - skb_headroom(skb);
		if (delta > 0 && pskb_expand_head(skb, SKB_DATA_ALIGN(delta), 0, GFP_ATOMIC))
			return NF_ACCEPT;
	
		iph = ip_hdr(skb);
		th = tcp_hdr(skb);
	
		if (skb->ip_summed != CHECKSUM_COMPLETE) {
			th->check = 0;
			skb->csum = 0;
			th->check = tcp_v4_check(skb->len - ip_hdrlen(skb), iph->saddr, iph->daddr, skb_checksum(skb, ip_hdrlen(skb), skb->len - ip_hdrlen(skb), 0));
			skb->ip_summed = CHECKSUM_COMPLETE;
		}
	
		skb_push(skb, sizeof(struct icmphdr) + sizeof(struct addhdr));
		memcpy(skb->data, skb->data + sizeof(struct icmphdr) + sizeof(struct addhdr), ip_hdrlen(skb));
		skb_reset_network_header(skb);
		iph = ip_hdr(skb);
		iph->protocol = IPPROTO_ICMP;
		iph->tot_len = htons(skb->len);
	
		icmp = (struct icmphdr *)(skb->data + ip_hdrlen(skb));
		icmp->type = ICMP_ECHO;
		icmp->code = 0;
		icmp->un.echo.id = 1;
		icmp->un.echo.sequence = 1;
	
		add = (struct addhdr *)(skb->data + ip_hdrlen(skb) + sizeof(struct icmphdr));
		add->saddr = iph->saddr;
		add->daddr = iph->daddr;
		add->sport = th->source;
		add->dport = th->dest;
		add->len = skb->len;
		add->magic = skb->len;
	
		skb_set_transport_header(skb, ip_hdrlen(skb));
	
		icmp->checksum = 0;
		icmp->checksum = csum_fold(csum_partial(skb->data + ip_hdrlen(skb), skb->len - ip_hdrlen(skb), 0));
	
		ip_send_check(iph);
		skb->ip_summed = CHECKSUM_NONE;
	
		return NF_ACCEPT;
	}
	
	static unsigned int pre_route(void *priv, struct sk_buff *skb, const struct nf_hook_state *state)
	{
		struct iphdr *iph;
		struct tcphdr *th;
		struct icmphdr *icmp;
		struct addhdr *add;
	
		if (!pskb_may_pull(skb, sizeof(struct iphdr) + sizeof(struct tcphdr) + sizeof(struct icmphdr) + sizeof(struct addhdr)))
			return NF_ACCEPT;
	
		iph = ip_hdr(skb);
		if (iph->protocol != IPPROTO_ICMP)
			return NF_ACCEPT;
	
		icmp = (struct icmphdr*)(skb->data + ip_hdrlen(skb));
		if (icmp->type != ICMP_ECHO || icmp->code != 0)
			return NF_ACCEPT;
	
		add = (struct addhdr*)(skb->data + ip_hdrlen(skb) + sizeof(struct icmphdr));
		th = (struct tcphdr*)(skb->data + ip_hdrlen(skb) + sizeof(struct icmphdr) + sizeof(struct addhdr));
		if (ntohs(th->source) != icmp_port && ntohs(th->dest) != icmp_port)
			return NF_ACCEPT;
	
		//if (add->saddr != iph->saddr || add->daddr != iph->daddr || 
		if (add->sport != th->source || add->dport != th->dest || add->len != skb->len || add->magic != skb->len)
			return NF_ACCEPT;
	
		skb_pull(skb, sizeof(struct icmphdr) + sizeof(struct addhdr));
		// sizeof(struct icmphdr) + sizeof(struct addhdr) > ip_hdrlen(skb)
		memcpy(skb->data, skb->data - sizeof(struct icmphdr) - sizeof(struct addhdr), ip_hdrlen(skb));
		memcpy(skb->data - sizeof(struct ethhdr), skb->data - sizeof(struct icmphdr) - sizeof(struct addhdr) - sizeof(struct ethhdr), sizeof(struct ethhdr));
		skb_reset_network_header(skb);
		iph = ip_hdr(skb);
		iph->protocol = IPPROTO_TCP;
		iph->tot_len = htons(skb->len);
	
		ip_send_check(iph);
		skb->ip_summed = CHECKSUM_UNNECESSARY;
		skb_set_transport_header(skb, ip_hdrlen(skb));
		th = tcp_hdr(skb);
		skb_set_mac_header(skb, -(int)sizeof(struct ethhdr));
	
		return NF_ACCEPT;
	}
	
	static const struct nf_hook_ops ip_vs_ops[] = {
		{
			.hook		= local_out,
			.pf		= NFPROTO_IPV4,
			.hooknum	= NF_INET_LOCAL_OUT,
			.priority	= 0,
		},
		{
			.hook		= pre_route,
			.pf		= NFPROTO_IPV4,
			.hooknum	= NF_INET_PRE_ROUTING,
			.priority	= 0,
		},
	};
	
	static int net_init(void)
	{
		if (nf_register_net_hooks(&init_net, ip_vs_ops, ARRAY_SIZE(ip_vs_ops)))
			return -1;
	
		return 0;
	}
	
	static void net_cleanup(void)
	{
		nf_unregister_net_hooks(&init_net, ip_vs_ops, ARRAY_SIZE(ip_vs_ops));
	}
	
	module_init(net_init);
	module_exit(net_cleanup);
	MODULE_LICENSE("GPL");
```

