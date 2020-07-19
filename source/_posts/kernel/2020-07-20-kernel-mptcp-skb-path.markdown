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

```
	mptcp_write_wakeup
	mptcp_write_xmit
	mptcp_retransmit_skb
		-> mptcp_skb_entail
			-> mptcp_save_dss_data_seq 设置seq
			-> tcp_add_write_queue_tail 或 tcp_transmit_skb
```

### 接收

```
	mptcp_data_ready
		-> mptcp_queue_skb
			-> tcp_queue_rcv(meta_sk, tmp1, 0, &fragstolen)
			-> tcp_data_queue_ofo(meta_sk, tmp1);
```
