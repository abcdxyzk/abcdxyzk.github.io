---
layout: post
title: "linux Tcp Small Queue(TSQ)实现"
date: 2018-03-23 00:29:00 +0800
comments: false
categories:
- 2018
- 2018~03
- kernel
- kernel~net
tags:
---

http://www.cnhalo.net/2016/09/13/linux-tcp-small-queue/

#### 目的

考虑以下场景

  有两个tcp，其中一个连接的cwnd非常大，应用程序尽可能地发包  
  或者有个应用程序一直往外无限制地发送udp包  

  如果没有一种机制公平地限定各个连接的发送数量，底层的qdisc/网卡队列就会被高发包率的应用占用，同时造成上层tcp计算RTT和cwnd的偏差，以及bufferbloat问题。

  尤其对于默认采用pfifo_fast qdisc算法来说非常常见， 因为基本上只使用一个队列(大多数流的TOS=0)

#### 解决及未解决

  因此在qdisc队列长度一定的情况下，让不同的流拥有相等的配额  
  当达到配额后就不允许该流继续发包， 此时包存在在上层协议的缓存中，不往qdisc上发  
  如果网卡发包完成后，释放skb的时候，如果发现该流达到配额了，就通过回调机制通知上层可以往qdisc上发了  

Tcp Small Queue(TSQ)也由此而来。但是只解决了tcp流之间的公平问题，并没有在udp等其他协议上实现。
如果udp发满了qdisc，还是会对其他流造成影响。

因此在有很多非tcp业务的机器上，需要配置使用其他qdisc算法结合tc命令配置

#### 配置

#### qdisc队列长度

  通过ifconfig eth0查看，其中的txqueuelen就是qdisc的队列长度, 默认1000个skb， 这时候GSO/TSO还没开始，因此如果开启GSO/TSO数据只会更多

  通过ifconfig eth0 txqueuelen 1500可以设置该长度，设置过长会导致bufferbloat问题

因此对于默认qfifo_fast算法，qdisc的长度是以GSO包为单位， 超过该长度在qdisc层就会丢弃该包

#### 每个流的配额

在linux 4.9上，默认是4个TSO的大小，256KB
```
	/* Default TSQ limit of four TSO segments */
	net.ipv4.tcp_limit_output_bytes = 262144
```

#### 判断是否超过限制

在tcp_write_xmit()中，会调用tcp_small_queue_check()来判断该tcp是否达到配额
tcp_small_queue_check()返回true的话则不发送，让skb继续留在发送队列中.
并且会在该sock中设置TSQ_THROTTLED标记，表示上层数据在等待qdisc空间

```
	static bool tcp_small_queue_check(struct sock *sk, const struct sk_buff *skb,
					  unsigned int factor)
	{
		unsigned int limit;
		limit = max(2 * skb->truesize, sk->sk_pacing_rate >> 10);	//每毫秒的速率，或者两个当前包的大小
		limit = min_t(u32, limit, sysctl_tcp_limit_output_bytes);	//默认最大256K
		limit <<= factor;	//在重传的话*2
		if (atomic_read(&sk->sk_wmem_alloc) > limit) {	//qdisc中的数据超过限制
			set_bit(TSQ_THROTTLED, &tcp_sk(sk)->tsq_flags);	//设置标记，标记当前sock没通过tsq检测
			/* It is possible TX completion already happened
			 * before we set TSQ_THROTTLED, so we must
			 * test again the condition.
			 */
			smp_mb__after_atomic();
			if (atomic_read(&sk->sk_wmem_alloc) > limit)
				return true;
		}
		return false;
	}
```

#### 发送完成释放skb

在达到qdisc配额前，tcp_transmit_skb会为所有的数据包设置skb->destructor=tcp_wfree,
在设备发送完数据释放skb的时候，tcp_wfree()被调用，并根据TSQ_THROTTLED来判断，是否有数据正在等待qdisc空间。
如果有数据包在等待，则把该数据包的sock，加入到percpu的列表中。
并设置tasklet任务，在下一个软中断中发送该sock中的数据包

```
	static int tcp_transmit_skb(struct sock *sk, struct sk_buff *skb, int clone_it,
				    gfp_t gfp_mask)
	{
		...
		skb->destructor = skb_is_tcp_pure_ack(skb) ? __sock_wfree : tcp_wfree;
		...
	}
	/*
	 * Write buffer destructor automatically called from kfree_skb.
	 * We can't xmit new skbs from this context, as we might already
	 * hold qdisc lock.
	 */
	void tcp_wfree(struct sk_buff *skb)
	{
		struct sock *sk = skb->sk;
		struct tcp_sock *tp = tcp_sk(sk);
		int wmem;
		/* Keep one reference on sk_wmem_alloc.
		 * Will be released by sk_free() from here or tcp_tasklet_func()
		 */
		wmem = atomic_sub_return(skb->truesize - 1, &sk->sk_wmem_alloc);
		/* If this softirq is serviced by ksoftirqd, we are likely under stress.
		 * Wait until our queues (qdisc + devices) are drained.
		 * This gives :
		 * - less callbacks to tcp_write_xmit(), reducing stress (batches)
		 * - chance for incoming ACK (processed by another cpu maybe)
		 *   to migrate this flow (skb->ooo_okay will be eventually set)
		 */
		if (wmem >= SKB_TRUESIZE(1) && this_cpu_ksoftirqd() == current)
			goto out;
		if (test_and_clear_bit(TSQ_THROTTLED, &tp->tsq_flags) &&	//判断并清除成功, 避免重复插入队列
		    !test_and_set_bit(TSQ_QUEUED, &tp->tsq_flags)) {// 设置TSQ_QUEUED
			unsigned long flags;
			struct tsq_tasklet *tsq;
			/* queue this socket to tasklet queue */
			local_irq_save(flags);
			tsq = this_cpu_ptr(&tsq_tasklet);
			list_add(&tp->tsq_node, &tsq->head); //添加sock到percpu列表
			tasklet_schedule(&tsq->tasklet);	//等待在softirq中被调度
			local_irq_restore(flags);
			return;
		}
	out:
		sk_free(sk);
	}
```

#### tasklet

在系统初始化的时候会初始化percpu的tsq tasklet列表

```
	/* TCP SMALL QUEUES (TSQ)
	 *
	 * TSQ goal is to keep small amount of skbs per tcp flow in tx queues (qdisc+dev)
	 * to reduce RTT and bufferbloat.
	 * We do this using a special skb destructor (tcp_wfree).
	 *
	 * Its important tcp_wfree() can be replaced by sock_wfree() in the event skb
	 * needs to be reallocated in a driver.
	 * The invariant being skb->truesize subtracted from sk->sk_wmem_alloc
	 *
	 * Since transmit from skb destructor is forbidden, we use a tasklet
	 * to process all sockets that eventually need to send more skbs.
	 * We use one tasklet per cpu, with its own queue of sockets.
	 */
	struct tsq_tasklet {
		struct tasklet_struct	tasklet;
		struct list_head	head; /* queue of tcp sockets */
	};
	static DEFINE_PER_CPU(struct tsq_tasklet, tsq_tasklet);
	void __init tcp_tasklet_init(void)
	{
		int i;
		for_each_possible_cpu(i) {
			struct tsq_tasklet *tsq = &per_cpu(tsq_tasklet, i);
			INIT_LIST_HEAD(&tsq->head);
			tasklet_init(&tsq->tasklet,
				     tcp_tasklet_func,
				     (unsigned long)tsq);
		}
	}
	void __init tcp_init(void)
	{
		...
		tcp_tasklet_init();
	}
```

#### tcp_tasklet_func

tcp_tasklet_func是实际的tasklet在softirq中被执行的函数
如果应用程序没有持有该sock锁， 则直接调用tcp_tsq_handler来发送等待的skb。
否则就在应用程序release_sock()的时候调用tcp_release_cb()，再用tcp_tsq_handler()发送skb

```
	/*
	 * One tasklet per cpu tries to send more skbs.
	 * We run in tasklet context but need to disable irqs when
	 * transferring tsq->head because tcp_wfree() might
	 * interrupt us (non NAPI drivers)
	 */
	static void tcp_tasklet_func(unsigned long data)
	{
		struct tsq_tasklet *tsq = (struct tsq_tasklet *)data;
		LIST_HEAD(list);
		unsigned long flags;
		struct list_head *q, *n;
		struct tcp_sock *tp;
		struct sock *sk;
		local_irq_save(flags);
		list_splice_init(&tsq->head, &list);	//tsq->head中的所有成员移动到list中
		local_irq_restore(flags);	//调用前关中断，现在恢复
		list_for_each_safe(q, n, &list) {	//遍历所有list成员
			tp = list_entry(q, struct tcp_sock, tsq_node);
			list_del(&tp->tsq_node);
			sk = (struct sock *)tp;
			bh_lock_sock(sk);
			if (!sock_owned_by_user(sk)) {
				tcp_tsq_handler(sk);
			} else {
				/* defer the work to tcp_release_cb() */
				set_bit(TCP_TSQ_DEFERRED, &tp->tsq_flags);
			}
			bh_unlock_sock(sk);
			clear_bit(TSQ_QUEUED, &tp->tsq_flags);
			sk_free(sk);
		}
	}
	static void tcp_tsq_handler(struct sock *sk)
	{
		if ((1 << sk->sk_state) &
		    (TCPF_ESTABLISHED | TCPF_FIN_WAIT1 | TCPF_CLOSING |
		     TCPF_CLOSE_WAIT  | TCPF_LAST_ACK)) {
			struct tcp_sock *tp = tcp_sk(sk);
			if (tp->lost_out > tp->retrans_out &&	//有丢的包还没重传
			    tp->snd_cwnd > tcp_packets_in_flight(tp))	//拥塞窗口还有配额
				tcp_xmit_retransmit_queue(sk);	//重传
			tcp_write_xmit(sk, tcp_current_mss(sk), tp->nonagle,	//发送
				       0, GFP_ATOMIC);
		}
	}
	#define TCP_DEFERRED_ALL ((1UL << TCP_TSQ_DEFERRED) |		\
				  (1UL << TCP_WRITE_TIMER_DEFERRED) |	\
				  (1UL << TCP_DELACK_TIMER_DEFERRED) |	\
				  (1UL << TCP_MTU_REDUCED_DEFERRED))
	/**
	 * tcp_release_cb - tcp release_sock() callback
	 * @sk: socket
	 *
	 * called from release_sock() to perform protocol dependent
	 * actions before socket release.
	 */
	void tcp_release_cb(struct sock *sk)
	{
		struct tcp_sock *tp = tcp_sk(sk);
		unsigned long flags, nflags;
		/* perform an atomic operation only if at least one flag is set */
		do {
			flags = tp->tsq_flags;
			if (!(flags & TCP_DEFERRED_ALL))
				return;
			nflags = flags & ~TCP_DEFERRED_ALL;
		} while (cmpxchg(&tp->tsq_flags, flags, nflags) != flags);
		if (flags & (1UL << TCP_TSQ_DEFERRED))
			tcp_tsq_handler(sk);
		/* Here begins the tricky part :
		 * We are called from release_sock() with :
		 * 1) BH disabled
		 * 2) sk_lock.slock spinlock held
		 * 3) socket owned by us (sk->sk_lock.owned == 1)
		 *
		 * But following code is meant to be called from BH handlers,
		 * so we should keep BH disabled, but early release socket ownership
		 */
		sock_release_ownership(sk);
		if (flags & (1UL << TCP_WRITE_TIMER_DEFERRED)) {
			tcp_write_timer_handler(sk);
			__sock_put(sk);
		}
		if (flags & (1UL << TCP_DELACK_TIMER_DEFERRED)) {
			tcp_delack_timer_handler(sk);
			__sock_put(sk);
		}
		if (flags & (1UL << TCP_MTU_REDUCED_DEFERRED)) {
			inet_csk(sk)->icsk_af_ops->mtu_reduced(sk);
			__sock_put(sk);
		}
	}
```

tcp_tsq_handler最终还是会调用tcp_write_xmit来发送， 还是需要通过tcp_small_queue_check()检测

#### 其他

另外tcp auto cork也使用tsq机制来实现延后发送

```
	static void tcp_push(struct sock *sk, int flags, int mss_now,
			     int nonagle, int size_goal)
	{
		...
		if (tcp_should_autocork(sk, skb, size_goal)) {
			//不发了，设置tsq标记后返回
			/* avoid atomic op if TSQ_THROTTLED bit is already set */
			if (!test_bit(TSQ_THROTTLED, &tp->tsq_flags)) {
				NET_INC_STATS(sock_net(sk), LINUX_MIB_TCPAUTOCORKING);
				set_bit(TSQ_THROTTLED, &tp->tsq_flags);
			}
			/* It is possible TX completion already happened
			 * before we set TSQ_THROTTLED.
			 */
			if (atomic_read(&sk->sk_wmem_alloc) > skb->truesize)
				return;
		}
		if (flags & MSG_MORE)	//应用程序标记了很快有新的数据到来，则标记cork，不发送小包
			nonagle = TCP_NAGLE_CORK;
		__tcp_push_pending_frames(sk, mss_now, nonagle);	//最终调用tcp_write_xmit
	}
```

