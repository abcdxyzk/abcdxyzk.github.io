---
layout: post
title: "MPTCP skb路径"
date: 2020-07-20 00:55:00 +0800
comments: false
categories:
- 2020
- 2020~07
- kernel
- kernel~mptcp
tags:
---

### 发送

tcp_sendmsg 将 skb 写入 meta_sk 的 sk_write_queue 然后复制一份skb，将 clone_skb 写入subsk的sk_write_queue。

相同的[seq, endseq]会同时存在meta_sk->sk_write_queue, meta_sk->reinject_queue, subsk->sk_write_queue

meta_sk->reinject_queue 跟 meta_sk->sk_write_queue 差不多，目前的pm中reinject_queue的发送优先级高于sk_write_queue。

reinject_queue 中skb的来源有：

1. 重传时调mptcp_reinject_data将skb放到meta_sk的reinject_queue，也就是一个subsk重传skb，可以放到另一个subsk

2. subsk 调 tcp_write_queue_purge 时可能这些skb还是要发出去的，所以把skb放到meta_sk的reinject_queue

3. mptcp_sub_retransmit_timer, mptcp_del_sock, mptcp_send_reset_rem_id 等

```
	mptcp_write_wakeup
		reinject = 0
	mptcp_write_xmit
		if (skb from reinject_queue)
			reinject = 1
		else
			reinject = 0
	mptcp_retransmit_skb
		reinject = -1

		-> mptcp_skb_entail(, skb, reinject)
			-> mptcp_save_dss_data_seq 设置seq
			-> tcp_add_write_queue_tail 或 tcp_transmit_skb


	mptcp_sub_retransmit_timer
	mptcp_del_sock
	mptcp_send_reset_rem_id
	tcp_write_queue_purge
		-> mptcp_reinject_data
			-> skb_queue_tail(meta_sk->reinject_queue, skb)
```

### 接收

```
	mptcp_data_ready
		-> mptcp_queue_skb
			-> tcp_queue_rcv(meta_sk, tmp1, 0, &fragstolen)
			-> tcp_data_queue_ofo(meta_sk, tmp1);

	tcp_validate_incoming
		-> mptcp_handle_options
			-> mptcp_process_data_ack
				-> mptcp_clean_rtx_queue
					-> 清理 meta_sk->sk_write_queue
					-> 清理 mpcb->reinject_queue
	tcp_ack
		-> tcp_clean_rtx_queue
			-> 清理 subsk->sk_write_queue
```

