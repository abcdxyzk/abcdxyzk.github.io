---
layout: post
title: "MPTCP 回复一样的option"
date: 2020-07-09 01:39:00 +0800
comments: false
categories:
- 2020
- 2020~07
- kernel
- kernel~mptcp
tags:
---

对方回复一模一样的option

#### 例如

curl ksurl.cn

```
	01:42:57.092471 IP 192.168.8.162.34366 > 103.102.200.3.80: Flags [S], seq 846976861, win 64240, options [mss 1460,nop,nop,sackOK,nop,wscale 7,mptcp capable csum {0xc7c6d84045bd8248}], length 0
	01:42:57.130413 IP 103.102.200.3.80 > 192.168.8.162.34366: Flags [S.], seq 668917669, ack 846976862, win 0, options [mss 1452,nop,nop,sackOK,nop,nop,nop,nop,mptcp capable csum {0xc7c6d84045bd8248}], length 0
	01:42:57.130498 IP 192.168.8.162.34366 > 103.102.200.3.80: Flags [.], ack 1, win 64240, options [mptcp capable csum {0xc7c6d84045bd8248,0xc7c6d84045bd8248},mptcp dss ack 1200875982], length 0
	01:42:57.130525 IP 192.168.8.162.34366 > 103.102.200.3.80: Flags [.], ack 1, win 64240, options [mptcp add-addr id 3 11.0.0.1,mptcp dss ack 1200875982], length 0
	01:42:57.616370 IP 192.168.8.162.34366 > 103.102.200.3.80: Flags [.], ack 1, win 64240, options [mptcp dss ack 1200875982], length 0
	01:42:57.654157 IP 103.102.200.3.80 > 192.168.8.162.34366: Flags [.], ack 1, win 29200, length 0
	01:42:58.612344 IP 192.168.8.162.34366 > 103.102.200.3.80: Flags [.], ack 1, win 64240, options [mptcp dss ack 1200875982], length 0
	01:42:58.650740 IP 103.102.200.3.80 > 192.168.8.162.34366: Flags [.], ack 1, win 29200, length 0
	01:43:00.560359 IP 192.168.8.162.34366 > 103.102.200.3.80: Flags [.], ack 1, win 64240, options [mptcp dss ack 1200875982], length 0
	01:43:00.598942 IP 103.102.200.3.80 > 192.168.8.162.34366: Flags [.], ack 1, win 29200, length 0
	...
```

#### 修复

```
	diff --git a/src/4.15.18/tcp_input.c b/src/4.15.18/tcp_input.c
	index 1c36791..397cb89 100644
	--- a/src/4.15.18/tcp_input.c
	+++ b/src/4.15.18/tcp_input.c
	@@ -5845,6 +5845,11 @@ static int tcp_rcv_synsent_state_process(struct sock *sk, struct sk_buff *skb,
	                if (tp->request_mptcp || mptcp(tp)) {
	                        int ret;
	 
	+                       if (!mptcp(tp) && mopt.saw_mpc) {
	+                               struct tcp_sock *meta_tp = tcp_sk(sk);
	+                               if (meta_tp->mptcp_loc_key == mopt.mptcp_sender_key)
	+                                       mopt.saw_mpc = 0;
	+                       }
	                        rcu_read_lock();
	                        local_bh_disable();
	                        ret = mptcp_rcv_synsent_state_process(sk, &sk,
```

### 修复后

curl ksurl.cn

```
	01:48:11.136480 IP 192.168.8.162.34388 > 103.102.200.3.80: Flags [S], seq 1334883078, win 65320, options [mss 1420,nop,nop,sackOK,nop,wscale 7,mptcp capable csum {0xa48a1610f304b3a}], length 0
	01:48:11.174632 IP 103.102.200.3.80 > 192.168.8.162.34388: Flags [S.], seq 2018132645, ack 1334883079, win 0, options [mss 1420,nop,nop,sackOK,nop,nop,nop,nop,mptcp capable csum {0xa48a1610f304b3a}], length 0
	01:48:11.174720 IP 192.168.8.162.34388 > 103.102.200.3.80: Flags [.], ack 1, win 65320, length 0
	01:48:11.213236 IP 103.102.200.3.80 > 192.168.8.162.34388: Flags [.], ack 1, win 29200, length 0
	01:48:11.213283 IP 192.168.8.162.34388 > 103.102.200.3.80: Flags [P.], seq 1:73, ack 1, win 65320, length 72: HTTP: GET / HTTP/1.1
	01:48:11.252192 IP 103.102.200.3.80 > 192.168.8.162.34388: Flags [.], ack 73, win 29200, length 0
	01:48:11.253261 IP 103.102.200.3.80 > 192.168.8.162.34388: Flags [P.], seq 1:397, ack 73, win 29200, length 396: HTTP: HTTP/1.1 302 Moved Temporarily
	01:48:11.253300 IP 192.168.8.162.34388 > 103.102.200.3.80: Flags [.], ack 397, win 64924, length 0
	01:48:11.253541 IP 192.168.8.162.34388 > 103.102.200.3.80: Flags [F.], seq 73, ack 397, win 64924, length 0
	01:48:11.292118 IP 103.102.200.3.80 > 192.168.8.162.34388: Flags [F.], seq 397, ack 74, win 29200, length 0
	01:48:11.292182 IP 192.168.8.162.34388 > 103.102.200.3.80: Flags [.], ack 398, win 64923, length 0
```

