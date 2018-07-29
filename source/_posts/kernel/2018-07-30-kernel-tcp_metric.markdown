---
layout: post
title: "ip tcp_metric, 链路状态历史"
date: 2018-07-30 01:35:00 +0800
comments: false
categories:
- 2018
- 2018~07
- kernel
- kernel~net
tags:
---

#### 开关
/proc/sys/net/ipv4/tcp_no_metrics_save

#### 命令
https://www.linux.org/docs/man8/ip-tcp_metrics.html

```
	NAME
	       ip-tcp_metrics - management for TCP Metrics

	SYNOPSIS
	       ip [ OPTIONS ] tcp_metrics { COMMAND | help }


	       ip tcp_metrics { show | flush } SELECTOR

	       ip tcp_metrics delete [ address ] ADDRESS

	       SELECTOR := [ [ address ] PREFIX ]
```

EXAMPLES
```
	   ip tcp_metrics show address 192.168.0.0/24
	       Shows the entries for destinations from subnet

	   ip tcp_metrics show 192.168.0.0/24
	       The same but address keyword is optional

	   ip tcp_metrics
	       Show all is the default action

	   ip tcp_metrics delete 192.168.0.1
	       Removes the entry for 192.168.0.1 from cache.

	   ip tcp_metrics flush 192.168.0.0/24
	       Removes entries for destinations from subnet

	   ip tcp_metrics flush all
	       Removes all entries from cache

	   ip -6 tcp_metrics flush all
	       Removes all IPv6 entries from cache keeping the IPv4 entries.
```

--------------

https://blog.csdn.net/dog250/article/details/52071132

在inet_peer/tcp_metrics_hash中记录通往一个IP地址的链路状况历史的metrics信息

#### 2.6.32版本内核

路由cache项记录了一个标准的二元组，它记如下：

```
	struct rtable
	{
		union
		{
			struct dst_entry    dst;
		} u;
		...
		// 以下为记录二元组的信息
		__be32            rt_dst;    /* Path destination    */
		__be32            rt_src;    /* Path source        */
		int            rt_iif;
	 
		/* Info on neighbour */
		__be32            rt_gateway;
	 
		/* Miscellaneous cached information */
		__be32            rt_spec_dst; /* RFC1122 specific destination */
		// peer很重要！
		struct inet_peer    *peer; /* long-living peer info */
	};
```

注意这个peer字段，很重要！peer结构体记如下：

```
	struct inet_peer
	{
		/* group together avl_left,avl_right,v4daddr to speedup lookups */
		struct inet_peer    *avl_left, *avl_right;
		__be32            v4daddr;    /* peer's address */
		__u16            avl_height;
		__u16            ip_id_count;    /* IP ID for the next packet */
		struct list_head    unused;
		__u32            dtime;        /* the time of last use of not
					         * referenced entries */
		atomic_t        refcnt;
		atomic_t        rid;        /* Frag reception counter */
		__u32            tcp_ts;
		unsigned long        tcp_ts_stamp;
	};
```

已经初见雏形了，peer里面记录了一些关于tcp的描述信息，这个可以指导TCP进行拥塞控制。我需要在peer结构体里面添加诸如init_cwnd，RTT，ssthresh之类的就好了，这些信息从哪来？从上次的连接中来，或者从所有之前的连接数据的移动指数平均而来！

在建立或者接受连接的时候，甚至在每次发送数据包的时候，都需要查找路由，然后在命中路由cache的时候，自然而然就取到了peer字段，然后就可以用peer字段里面的数据指导TCP连接了，可以说，这个数据仅仅对TCP初始拥塞控制参数有效，其后的数据还是在本连接内学习为好。


#### 3.10内核, inet_peer结构体依然存在，只是不再用它了

路由cache在3.5之后被去除了，因此rtable也就和peer脱了钩。

```
	struct rtable {
		struct dst_entry    dst;
	 
		int            rt_genid;
		unsigned int        rt_flags;
		__u16            rt_type;
		__u8            rt_is_input;
		__u8            rt_uses_gateway;
	 
		int            rt_iif;
	 
		/* Info on neighbour */
		__be32            rt_gateway;
	 
		/* Miscellaneous cached information */
		u32            rt_pmtu;
	 
		struct list_head    rt_uncached;
	};
```

inet_peer从此变成了一个独立的东西，随用随取，取到则用，取不到则罢。inet_getpeer接口非常好用，它完成以下措施：

1.如果二元组不存在，可以创建；

2.如果二元组存在，则立即取到。

这就是说，你可以调用唯一的这个接口完成查询，创建操作，至于销毁，完全靠系统的一个定时器来负责，完全不用用户操心。在认同了inet_peer框架带来的福音之后，我们再来看inet_peer结构体与2.6.32内核的不同：

```
	struct inet_peer {
		/* group together avl_left,avl_right,v4daddr to speedup lookups */
		struct inet_peer __rcu    *avl_left, *avl_right;
		struct inetpeer_addr    daddr;
		__u32            avl_height;
	 
		// 此为关键！
		u32            metrics[RTAX_MAX];
		u32            rate_tokens;    /* rate limiting for ICMP */
		unsigned long        rate_last;
		union {
			struct list_head    gc_list;
			struct rcu_head     gc_rcu;
		};
		/*
		 * Once inet_peer is queued for deletion (refcnt == -1), following fields
		 * are not available: rid, ip_id_count
		 * We can share memory with rcu_head to help keep inet_peer small.
		 */
		union {
			struct {
				atomic_t            rid;        /* Frag reception counter */
				atomic_t            ip_id_count;    /* IP ID for the next packet */
			};
			struct rcu_head         rcu;
			struct inet_peer    *gc_next;
		};
	 
		/* following fields might be frequently dirtied */
		__u32            dtime;    /* the time of last use of not referenced entries */
		atomic_t        refcnt;
	};
```

注意metrics字段！看看RTAX_MAX即可：

```
	enum {
		RTAX_UNSPEC,
	#define RTAX_UNSPEC RTAX_UNSPEC
		RTAX_LOCK,
	#define RTAX_LOCK RTAX_LOCK
		RTAX_MTU,
	#define RTAX_MTU RTAX_MTU
		RTAX_WINDOW,
	#define RTAX_WINDOW RTAX_WINDOW
		RTAX_RTT,
	#define RTAX_RTT RTAX_RTT
		RTAX_RTTVAR,
	#define RTAX_RTTVAR RTAX_RTTVAR
		RTAX_SSTHRESH,
	#define RTAX_SSTHRESH RTAX_SSTHRESH
		RTAX_CWND,
	#define RTAX_CWND RTAX_CWND
		RTAX_ADVMSS,
	#define RTAX_ADVMSS RTAX_ADVMSS
		RTAX_REORDERING,
	#define RTAX_REORDERING RTAX_REORDERING
		RTAX_HOPLIMIT,
	#define RTAX_HOPLIMIT RTAX_HOPLIMIT
		RTAX_INITCWND,
	#define RTAX_INITCWND RTAX_INITCWND
		RTAX_FEATURES,
	#define RTAX_FEATURES RTAX_FEATURES
		RTAX_RTO_MIN,
	#define RTAX_RTO_MIN RTAX_RTO_MIN
		RTAX_INITRWND,
	#define RTAX_INITRWND RTAX_INITRWND
		__RTAX_MAX
	};
	 
	#define RTAX_MAX (__RTAX_MAX - 1)
```

几乎涵盖了大多数的TCP拥塞控制参数，这简直是荒漠甘泉！然而，然而我发现这个inet_peer框架几乎没有被调用的地方。这又是为何？这又一次在逼我重新造轮子吗？...从中，我看到了社区里面的点滴，inet_peer结构体依然存在，只是不再用它了，作为替代，作为替代一定有新的东西完成inet_peer的功能并且甚之！

#### 3.10内核中，tcp_metrics_hash占据了主角

不同于inet_peer，在既有的3.10内核中，tcp_metrics_hash占据了主角，仔细看看这个架构，感觉还是比inet_peer好，比之更加正式。这个接口是靠以下两个维护的：

tcp_get_metrics

tcp_update_metrics

这个metrics框架也是一个类似inet_peer一样全局的信息记录，但是功能跟inet_peer有些重复。在TCP连接初始之时，调用tcp_get_metrics获取TCP拥塞参数，然后在TCP连接结束的时候，会调用tcp_update_metrics来更新metrics，这个貌似更加合理。


