---
layout: post
title: "reuseport使用"
date: 2020-05-25 17:07:00 +0800
comments: false
categories:
- 2020
- 2020~05
- kernel
- kernel~net
tags:
---

http://www.it165.net/os/html/201605/17066.html

### Q&A

#### Q1：什么是reuseport？

A1：reuseport是一种套接字复用机制，它允许你将多个套接字bind在同一个IP地址/端口对上，这样一来，就可以建立多个服务来接受到同一个端口的连接。

#### Q2：当来了一个连接时，系统怎么决定到底是哪个套接字来处理它？

A2：对于不同的内核，处理机制是不一样的，总的说来，reuseport分为两种模式，即热备份模式和负载均衡模式，在早期的内核版本中，即便是加入对reuseport选项的支持，也仅仅为热备份模式，而在3.9内核之后，则全部改为了负载均衡模式，两种模式没有共存

#### Q3：什么是热备份模式和负载均衡模式呢？

A3：
热备份模式：即你创建了N个reuseport的套接字，然而工作的只有一个，其它的作为备份，只有当前一个套接字不再可用的时候，才会由后一个来取代，其投入工作的顺序取决于实现。

负载均衡模式：即你创建的所有N个reuseport的套接字均可以同时工作，当连接到来的时候，系统会取一个套接字来处理它。这样就可以达到负载均衡的目的，降低某一个服务的压力。

#### Q4：到底怎么取套接字呢？

A4：这个对于热备份模式和负载均衡模式是不同的。

热备份模式：一般而言，会将所有的reuseport同一个IP地址/端口的套接字挂在一个链表上，取第一个即可，如果该套接字挂了，它会被从链表删除，然后第二个便会成为第一个。

负载均衡模式：和热备份模式一样，所有reuseport同一个IP地址/端口的套接字会挂在一个链表上，你也可以认为是一个数组，这样会更加方便，当有连接到来时，用数据包的源IP/源端口作为一个HASH函数的输入，将结果对reuseport套接字数量取模，得到一个索引，该索引指示的数组位置对应的套接字便是工作套接字。

### 关于REUSEPORT的实现

Linux 4.5/4.6所谓的对reuseport的优化主要体现在查询速度上，在优化前，在HASH冲突链表上遍历所有的套接字之后才能知道到底取哪个(基于一种冒泡的score打分机制，不完成一轮冒泡遍历，不能确定谁的score最高)，之所以如此低效是因为内核将reuseport的所有套接字和其它套接字混合在了一起，查找是平坦的，正常的做法应该是将它们分为一个组，进行分层查找，先找到这个组(这个很容易)，然后再在组中找具体的套接字。Linux 4.5针对UDP做了上述优化，而Linux 4.6则将这个优化引入到了TCP。

设想系统中一共有10000个套接字被HASH到同一个冲突链表，其中9950个是reuseport的同一组套接字，如果按照老的算法，需要遍历10000个套接字，如果使用基于分组的算法，最多只需要遍历51个套接字即可，找到那个组之后，一步HASH就可以找到目标套接字的索引！

#### Linux 4.5之前的reuseport查找实现(4.3内核)

以下是未优化前的Linux 4.3内核的实现，可见是多么地不直观。它采用了遍历HASH冲突链表的方式进行reuseport套接字的精确定位：

```
	result = NULL;
	badness = 0;
	udp_portaddr_for_each_entry_rcu(sk, node, &hslot2->head) {
		score = compute_score2(sk, net, saddr, sport, daddr, hnum, dif);
		if (score > badness) { // 冒泡排序
			// 找到了更加合适的socket，需要重新hash
			result = sk;
			badness = score;
			reuseport = sk->sk_reuseport;
			if (reuseport) {
				hash = udp_ehashfn(net, daddr, hnum, saddr, sport);
				matches = 1;
			}
		} else if (score == badness && reuseport) { // reuseport套接字散列定位
			// 找到了同样reuseport的socket，进行定位
			matches++;
			if (reciprocal_scale(hash, matches) == 0)
				result = sk;
			hash = next_pseudo_random32(hash);
		}
	}
```

之所以要遍历是因为所有的reuseport套接字和其它的套接字都被平坦地插入到同一个表中，事先并不知道有多少组reuseport套接字以及每一组中有多少个套接字


#### Linux 4.5(针对UDP)/4.6(针对TCP)的reuseport查找实现

我们来看看在4.5和4.6内核中对于reuseport的查找增加了一些什么神奇的新东西：
```
	result = NULL;
	badness = 0;
	udp_portaddr_for_each_entry_rcu(sk, node, &hslot2->head) {
		score = compute_score2(sk, net, saddr, sport, daddr, hnum, dif);
		if (score > badness) {
			// 在reuseport情形下，意味着找到了更加合适的socket组，需要重新hash
			result = sk;
			badness = score;
			reuseport = sk->sk_reuseport;
			if (reuseport) {
				hash = udp_ehashfn(net, daddr, hnum, saddr, sport);
				if (select_ok) {
					struct sock *sk2;
					// 找到了一个组，接着进行组内hash。
					sk2 = reuseport_select_sock(sk, hash, skb, sizeof(struct udphdr));
					if (sk2) {
						result = sk2;
						select_ok = false;
						goto found;
					}
				}
				matches = 1;
			}
		} else if (score == badness && reuseport) {
			// 这个else if分支的期待是，在分层查找不适用的时候，寻找更加匹配的reuseport组，注意4.5/4.6以后直接寻找的是一个reuseport组。
			// 在某种意义上，这回退到了4.5之前的算法。
			matches++;
			if (reciprocal_scale(hash, matches) == 0)
				result = sk;
			hash = next_pseudo_random32(hash);
		}
	}
```

我们着重看一下reuseport_select_sock，这个函数是第二层组内查找的关键，其实不应该叫做查找，而应该叫做定位更加合适：
```
	struct sock *reuseport_select_sock(struct sock *sk, u32 hash, struct sk_buff *skb, int hdr_len)
	{
		...
		prog = rcu_dereference(reuse->prog);
		socks = READ_ONCE(reuse->num_socks);
		if (likely(socks)) {
			/* paired with smp_wmb() in reuseport_add_sock() */
			smp_rmb();
 
			if (prog && skb) // 可以用BPF来从用户态注入自己的定位逻辑，更好实现基于策略的负载均衡
				sk2 = run_bpf(reuse, socks, prog, skb, hdr_len);
			else
				// reciprocal_scale简单地将结果限制在了[0,socks)这个区间内
				sk2 = reuse->socks[reciprocal_scale(hash, socks)];
		}
		...
	}
```

