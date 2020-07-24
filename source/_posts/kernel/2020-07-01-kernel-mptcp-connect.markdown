---
layout: post
title: "mptcp建连过程"
date: 2020-07-01 22:26:00 +0800
comments: false
categories:
- 2020
- 2020~07
- kernel
- kernel~mptcp
tags:
- mptcp
---

### 创建 socket

```
	inet_create
		tcp_v4_init_sock
			tcp_init_sock
				mptcp_init_tcp_sock {
					if (!mptcp_init_failed && sysctl_mptcp_enabled == MPTCP_SYSCTL)
						mptcp_enable_sock(sk);
				}
```

所以listen之后再设置mptcp_enable=0，需要restart才能生效

### 发送syn

只加 option

```
	tcp_connect {
		tcp_connect_init {
			tp->request_mptcp = 1;
		}
		tcp_transmit_skb
			tcp_syn_options
				mptcp_syn_options {
					if (is_master_tp(tp)) {
						opts->mptcp_options |= OPTION_MP_CAPABLE | OPTION_TYPE_SYN;
						...
					} else {
						opts->mptcp_options |= OPTION_MP_JOIN | OPTION_TYPE_SYN;
					}
				}
	}
```

### 接收syn, 发送synack

只加 option

```
	tcp_synack_options
		mptcp_synack_options {
			/* MPCB not yet set - thus it's a new MPTCP-session */
			if (!mtreq->is_sub) {
				opts->mptcp_options |= OPTION_MP_CAPABLE | OPTION_TYPE_SYNACK;
			} else {
				opts->mptcp_options |= OPTION_MP_JOIN | OPTION_TYPE_SYNACK;
			}
		}
```

### 接收synack

```
	tcp_v4_do_rcv {
		sk->sk_state == TCP_SYN_SENT

		tcp_rcv_state_process {
			queued = tcp_rcv_synsent_state_process(sk, skb, th, len) {

				if (tp->request_mptcp || mptcp(tp)) {
					ret = mptcp_rcv_synsent_state_process(sk, &sk, skb, &mopt);
					// 这个会创建出一个新的sk叫master_sk，原来的sk改为meta_sk
					// master_sk 和 meta_sk 的五元组一样，meta_sk 从hash表中删去，master_sk 加入hash表
					// 也就是说，和应用层通信的是meta_sk，tcp通信用master_sk
				}

				tcp_set_state(sk, TCP_ESTABLISHED);

				tcp_send_ack(sk) {
					mptcp_established_options {
						if (unlikely(tp->mptcp->include_mpc)) {
							opts->mptcp_options |= OPTION_MP_CAPABLE | OPTION_TYPE_ACK;
						}
				}
			}
			if (is_meta_sk(sk)) {
				mptcp_update_metasocket(tp->meta_sk);
				// 客户端建连成功
			}
		}
	}
```

### 接收ack

```
	tcp_child_process {
		tcp_rcv_state_process {

			if (!tcp_validate_incoming(sk, skb, th, 0))
				return 0;

			/* step 5: check the ACK field */
			if (th->ack) {
				int acceptable = tcp_ack(sk, skb, FLAG_SLOWPATH) > 0;

				switch (sk->sk_state) {
					case TCP_SYN_RECV:

					tcp_set_state(sk, TCP_ESTABLISHED);

				}

				case TCP_ESTABLISHED:
					tcp_data_queue(sk, skb);
					queued = 1;
					break;
				}

			}

			if (mptcp(tp)) {
				if (is_master_tp(tp)) {
					mptcp_update_metasocket(mptcp_meta_sk(sk));
					// 服务端建连成功
				}
		}
```

