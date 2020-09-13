---
layout: post
title: "MPTCP pre_established fully_established"
date: 2020-07-19 01:37:00 +0800
comments: false
categories:
- 2020
- 2020~07
- kernel
- kernel~mptcp
tags:
---

### 一、pre_established

只在subflow的客户端起作用，在收到synack时置为1，收到第4个ack时置为0，防止在synack到第四个ack期间发送数据包。

因为服务端要用第三个ack建连，客户端收到第四个ack表示建连成功，成功之后才能发数据

#### mptcp_ack_timer

所以客户端需要 mptcp_ack_timer，不停的发送第三个ack，直到收到第四个ack


### 二、fully_established

fully_established 和 pre_established 互不相关

mptcp需要四次握手，四次握手完成后 fully_established=1, 再之后才能建立subflow


tcp三次握手后，client和server两边的 fully_established = 0, 进入fully_established的条件如下：

1. 本端没发送数据包，但一直收到对端的mptcp数据包，见 mptcp_prevalidate_skb()
```
	if (!tp->mptcp->fully_established) {
		tp->mptcp->init_rcv_wnd -= skb->len;
		if (tp->mptcp->init_rcv_wnd < 0)
			mptcp_become_fully_estab(sk);
	}
```

2. 本端发出去的mptcp数据包被mptcp_ack了，见 mptcp_process_data_ack
```
	if (unlikely(!tp->mptcp->fully_established) && tp->mptcp->snt_isn + 1 != TCP_SKB_CB(skb)->ack_seq)
		mptcp_become_fully_estab(sk);
```

3. 如果收到非mptcp数据包，则回退普通tcp，回退也会设置fully_established=1，见mptcp_prevalidate_skb()
```
	if (!tp->mptcp->fully_established && !mptcp_is_data_seq(skb) &&
		!tp->mptcp->mapping_present && !mpcb->infinite_mapping_rcv) {
		...
		if (!is_master_tp(tp)) { // subflow reset，master才回退
			MPTCP_INC_STATS(sock_net(sk), MPTCP_MIB_FBDATASUB);
			mptcp_send_reset(sk);
			return 1;
		}
		...
		tp->mptcp->fully_established = 1;
	}
```

4. 如果本端发出去的数据包被不带mptcp的ack ack了，那么大概率是对端没建立mptcp连接。那么本端回退到普通tcp，回退也会设置fully_established=1。

```
	tcp_ack() {
		mptcp_fallback_infinite() {
			if (likely(tp->mptcp->fully_established))
				return false;

			if (!(flag & MPTCP_FLAG_DATA_ACKED)) // 被ack的包一定是mptcp数据包
				return false;

			if (!is_master_tp(tp)) { // subflow reset，master才回退
				MPTCP_INC_STATS(sock_net(sk), MPTCP_MIB_FBACKSUB);
				return true;
			}
			...
		}
	}
```

因为 fully_established = 0 时刚完成3次握手，所以上面说的数据包基本都是第一个数据包

以上条件对于client和server都适用，因为3次握手后谁先发包都是正常的。

### mptcp 连接在 fully_established=1 之后再收到 不含mptcp option的包

1. 不会进行mapping，见mptcp_detect_mapping()

```
	if (!mptcp_is_data_seq(skb)) {
		if (!tp->mptcp->mapping_present && tp->rcv_nxt - tp->copied_seq > 65536) {
			mptcp_send_reset(sk);
			return 1;
		}
		return 0;
	}
```

2. 对于一个map_data_len包，可能被差成了多个包传输：

如果多个包全不是mptcp包，则mapping_present=0，那么mptcp_queue_skb() 会直接return，然后 tp->rcv_nxt - tp->copied_seq > 65536, 然后被reset

如果前一部分是mptcp的包，后一部分不是mptcp包，则mapping_present=1，然后会被mptcp_verif_dss_csum() reset

