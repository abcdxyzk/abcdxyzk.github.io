---
layout: post
title: "MPTCP 64bit seq"
date: 2020-07-20 00:16:00 +0800
comments: false
categories:
- 2020
- 2020~07
- kernel
- kernel~mptcp
tags:
---

### 一、snd_high_order, rcv_high_order

发送和接收都将seq映射到64位上，这样能防止不同子流之间seq造成的歧义。

```
	# 发送
	static inline __be32 mptcp_get_highorder_sndbits(const struct sk_buff *skb, const struct mptcp_cb *mpcb)
	{
		return htonl(mpcb->snd_high_order[(TCP_SKB_CB(skb)->mptcp_flags &
				MPTCPHDR_SEQ64_INDEX) ? 1 : 0]);
	}

	static inline void mptcp_check_sndseq_wrap(struct tcp_sock *meta_tp, int inc)
	{
		if (unlikely(meta_tp->snd_nxt > meta_tp->snd_nxt + inc)) {
			struct mptcp_cb *mpcb = meta_tp->mpcb;
			mpcb->snd_hiseq_index = mpcb->snd_hiseq_index ? 0 : 1;
			mpcb->snd_high_order[mpcb->snd_hiseq_index] += 2;
		}
	}

	# 接收
	static inline u64 mptcp_get_data_seq_64(const struct mptcp_cb *mpcb, int index, u32 data_seq_32)
	{
		return ((u64)mpcb->rcv_high_order[index] << 32) | data_seq_32;
	}

	static inline u64 mptcp_get_rcv_nxt_64(const struct tcp_sock *meta_tp)
	{
		struct mptcp_cb *mpcb = meta_tp->mpcb;
		return mptcp_get_data_seq_64(mpcb, mpcb->rcv_hiseq_index,
					     meta_tp->rcv_nxt);
	}

	static inline void mptcp_check_rcvseq_wrap(struct tcp_sock *meta_tp, u32 old_rcv_nxt)
	{
		if (unlikely(old_rcv_nxt > meta_tp->rcv_nxt)) {
			struct mptcp_cb *mpcb = meta_tp->mpcb;
			mpcb->rcv_high_order[mpcb->rcv_hiseq_index] += 2;
			mpcb->rcv_hiseq_index = mpcb->rcv_hiseq_index ? 0 : 1;
		}
	}
```

#### 1. 发送端 MPTCPHDR_SEQ64_INDEX
MPTCPHDR_SEQ64_INDEX 在发送和接收上有不同用法，在发送上

```
	static bool mptcp_skb_entail(struct sock *sk, struct sk_buff *skb, int reinject)
	{
		...
		if (!reinject) // 如果是第一次发送的包, MPTCPHDR_SEQ64_INDEX 只是作为 snd_hiseq_index 的替代
			TCP_SKB_CB(skb)->mptcp_flags |= (mpcb->snd_hiseq_index ? MPTCPHDR_SEQ64_INDEX : 0);
		...
```

#### 2. wrap
在 mptcp_check_sndseq_wrap 中 snd_hiseq_index ^= 1, 然后 snd_high_order[i] += 2; 所以 snd_high_order使用 snd_high_order[i] 和 snd_high_order[i-1]。

在 mptcp_check_rcvseq_wrap 中 rcv_high_order[i] += 2; rcv_hiseq_index ^= 1; 所以 rcv_high_order 使用 rcv_high_order[i] 和 rcv_high_order[i+1]。

为什么？

因为发送的时候只需要用到最高seq(snd_nxt)，但接收的时候会超高最高seq(rcv_nxt)。在 mptcp_detect_mapping 中指明了：
```
	if (unlikely(after(data_seq, meta_tp->rcv_nxt) && data_seq < meta_tp->rcv_nxt)) {
		tp->mptcp->map_data_seq = mptcp_get_data_seq_64(mpcb, !mpcb->rcv_hiseq_index, data_seq);
```


### 二、64bit OR 33bit

#### 1. 接收端 MPTCPHDR_SEQ64_INDEX

在 mptcp_write_dss_data_ack() 中 mdss->m = 0; 所以 MPTCPHDR_SEQ64_SET 永远不启用。 接收端只有在 MPTCPHDR_SEQ64_SET 启用时 MPTCPHDR_SEQ64_INDEX, MPTCPHDR_SEQ64_OFO 才有用， 见 mptcp_get_64_bit

```
	static inline u8 mptcp_get_64_bit(u64 data_seq, struct mptcp_cb *mpcb)
	{
		u64 data_seq_high = (u32)(data_seq >> 32);

		if (mpcb->rcv_high_order[0] == data_seq_high)
			return 0;
		else if (mpcb->rcv_high_order[1] == data_seq_high)
			return MPTCPHDR_SEQ64_INDEX;
		else
			return MPTCPHDR_SEQ64_OFO;
	}

	static inline __u32 *mptcp_skb_set_data_seq(const struct sk_buff *skb, u32 *data_seq, struct mptcp_cb *mpcb)
	{
		__u32 *ptr = (__u32 *)(skb_transport_header(skb) + TCP_SKB_CB(skb)->dss_off);

		if (TCP_SKB_CB(skb)->mptcp_flags & MPTCPHDR_SEQ64_SET) {
			u64 data_seq64 = get_unaligned_be64(ptr);

			if (mpcb)
				TCP_SKB_CB(skb)->mptcp_flags |= mptcp_get_64_bit(data_seq64, mpcb);

			*data_seq = (u32)data_seq64;
			ptr++;
		} else {
			*data_seq = get_unaligned_be32(ptr);
		}

		return ptr;
	}
```

#### 2. bug??
```
	if (mpcb->rcv_high_order[0] == data_seq_high)
		return 0;
	else if (mpcb->rcv_high_order[1] == data_seq_high)
		return MPTCPHDR_SEQ64_INDEX;
```
这四句应该改成:
```
	i = mpcb->rcv_hiseq_index;
	if (mpcb->rcv_high_order[i] == data_seq_high)
		return 0;
	else if (mpcb->rcv_high_order[i^1] == data_seq_high)
		return MPTCPHDR_SEQ64_INDEX;
```

#### 3. 33bit
```
	rcv_high_order[i^1] = rcv_high_order[i] + 1;
```
所以所谓的64bit，其实是33bit。

#### 4. MPTCPHDR_SEQ64_OFO

33bit seq 超过了 `rcv_high_order[i^1]`，判定为无效数据，不收取
