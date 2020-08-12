---
layout: post
title: "MPTCP DSS && MPTCPHDR_INF"
date: 2020-07-19 23:33:00 +0800
comments: false
categories:
- 2020
- 2020~07
- kernel
- kernel~mptcp
tags:
---

### dss=Data Sequence Signal

用于将子流的seq映射到主流上。

三次握手后 maskter_sk = meta_sk, 然后 meta_sk 会重新分配seq, snd_nxt, rcv_nxt, write_seq, copied_seq 等。

### master_sk, subflow 的seq和 meta_sk 建立联系

#### output

```
	mptcp_save_dss_data_seq {
		mptcp_write_dss_data_ack
		mptcp_write_dss_mapping
	}
```

先写ACK映射，再写DATA映射。

```
	static int mptcp_write_dss_data_ack(const struct tcp_sock *tp, const struct sk_buff *skb,
					    __be32 *ptr)
	{
		struct mp_dss *mdss = (struct mp_dss *)ptr;
		__be32 *start = ptr; 

		mdss->kind = TCPOPT_MPTCP;
		mdss->sub = MPTCP_SUB_DSS;
		mdss->rsv1 = 0; 
		mdss->rsv2 = 0; 
		mdss->F = mptcp_is_data_fin(skb) ? 1 : 0; 
		mdss->m = 0; 
		mdss->M = mptcp_is_data_seq(skb) ? 1 : 0; 
		mdss->a = 0; 
		mdss->A = 1; 
		mdss->len = mptcp_sub_len_dss(mdss, tp->mpcb->dss_csum);
		ptr++;

		*ptr++ = htonl(mptcp_meta_tp(tp)->rcv_nxt);

		return ptr - start;
	}
```


```
	static int mptcp_write_dss_mapping(const struct tcp_sock *tp, const struct sk_buff *skb,
					   __be32 *ptr)
	{
		const struct tcp_skb_cb *tcb = TCP_SKB_CB(skb);
		__be32 *start = ptr; 
		__u16 data_len;

		*ptr++ = htonl(tcb->seq); /* data_seq */

		/* If it's a non-data DATA_FIN, we set subseq to 0 (draft v7) */
		if (mptcp_is_data_fin(skb) && skb->len == 0)
			*ptr++ = 0; /* subseq */
		else 
			*ptr++ = htonl(tp->write_seq - tp->mptcp->snt_isn); /* subseq */

		if (tcb->mptcp_flags & MPTCPHDR_INF)
			data_len = 0; 
		else 
			data_len = tcb->end_seq - tcb->seq;

		if (tp->mpcb->dss_csum && data_len) {
			__sum16 *p16 = (__sum16 *)ptr;
			__be32 hdseq = mptcp_get_highorder_sndbits(skb, tp->mpcb);
			__wsum csum;

			*ptr = htonl(((data_len) << 16) |
				     (TCPOPT_EOL << 8) | 
				     (TCPOPT_EOL));
			csum = csum_partial(ptr - 2, 12, skb->csum);
			p16++;
			*p16++ = csum_fold(csum_partial(&hdseq, sizeof(hdseq), csum));
		} else {
			*ptr++ = htonl(((data_len) << 16) |
				       (TCPOPT_NOP << 8) | 
				       (TCPOPT_NOP));
		}

		return ptr - start;
	}
```

#### input

* 收到的包有可能被中间设备分成多个包，或由于gso、tso、gro造成收发包大小不一一对应。所以在接收端能看到很多 skb_queue_walk_safe(&sk->sk_receive_queue, skb, tmp)

映射处理顺序：
mptcp_data_ready -> mptcp_prevalidate_skb, mptcp_detect_mapping, mptcp_validate_mapping


##### mptcp_detect_mapping

发送一个包可能对应多个接收包，在接收第一个包的时候设置好
```
	tp->mptcp->map_data_len = data_len;
	tp->mptcp->map_subseq = sub_seq;
	tp->mptcp->map_data_fin = mptcp_is_data_fin(skb) ? 1 : 0;
	tp->mptcp->mapping_present = 1;
```

##### mptcp_queue_skb

处理完一个或多个接收包（=一个发送包）后调mptcp_reset_mapping，重置 map_data_len，map_data_seq，map_subseq，map_data_fin，mapping_present。


### MPTCPHDR_INF 模式

MPTCPHDR_INF 模式是取消子流seq，退避回普通tcp，通通让meta_sk处理。

infinite 模式正常不开启的

#### 开启条件
1. dss_csum != 0 并且没有established连接，见 mptcp_verif_dss_csum()

2. 进入 mptcp_mp_fail_rcvd()

3. 接收到数据时还没established，进入INF模式。见 mptcp_prevalidate_skb()

#### 参数

send_infinite_mapping = 1 发送端出错进入inf模式，需要发送数据通知接收端

infinite_mapping_snd = 1 发送端进入INF模式

infinite_mapping_rcv = 1 接收端进入INF模式, 接收seq映射改用 infinite_rcv_seq

```
	mptcp_detect_mapping()
	{
		if (!data_len) {
			...
			set_infinite_rcv = true;
			...
		}

		...
		if (set_infinite_rcv)
			mpcb->infinite_rcv_seq = tp->mptcp->map_data_seq;
	}
```

