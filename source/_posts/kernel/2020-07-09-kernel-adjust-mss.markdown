---
layout: post
title: "将包减小到mss以下"
date: 2020-07-09 01:57:00 +0800
comments: false
categories:
- 2020
- 2020~07
- kernel
- kernel~net
tags:
---

```
	diff --git a/src/4.15.18/tcp_output.c b/src/4.15.18/tcp_output.c
	index 82613f5..270545e 100644
	--- a/src/4.15.18/tcp_output.c
	+++ b/src/4.15.18/tcp_output.c
	@@ -52,6 +52,7 @@
	 #include "fec_core.h"
	 
	 u32 sysctl_post_local = 0xffffff00;
	+int sysctl_mss_adjust = 0;
	 
	 static bool tcp_write_xmit(struct sock *sk, unsigned int mss_now, int nonagle,
	 			   int push_one, gfp_t gfp);
	@@ -1720,6 +1721,8 @@ unsigned int tcp_current_mss(struct sock *sk)
	 			mss_now = tcp_sync_mss(sk, mtu);
	 	}
	 
	+	mss_now -= sysctl_mss_adjust;
	+
	 	header_len = tcp_established_options(sk, NULL, &opts, &md5, getconninfo(sk)) +
	 		     sizeof(struct tcphdr);
	 	/* The mss_cache is sized based on tp->tcp_header_len, which assumes
	@@ -3401,6 +3404,7 @@ struct sk_buff *tcp_make_synack(const struct sock *sk, struct dst_entry *dst,
	 	skb_dst_set(skb, dst);
	 
	 	mss = tcp_mss_clamp(tp, dst_metric_advmss(dst));
	+	mss -= sysctl_mss_adjust;
	 
	 	memset(&opts, 0, sizeof(opts));
	 #ifdef CONFIG_SYN_COOKIES
	@@ -3561,6 +3565,7 @@ static void tcp_connect_init(struct sock *sk)
	 	if (!tp->window_clamp)
	 		tp->window_clamp = dst_metric(dst, RTAX_WINDOW);
	 	tp->advmss = tcp_mss_clamp(tp, dst_metric_advmss(dst));
	+	tp->advmss -= sysctl_mss_adjust;
	 
	 	tcp_initialize_rcv_mss(sk);
	 
	diff --git a/src/io_sysctl.c b/src/io_sysctl.c
	index c3b2ddd..6fdc1df 100644
	--- a/src/io_sysctl.c
	+++ b/src/io_sysctl.c
	@@ -9,6 +9,7 @@ extern int sysctl_detail;
	 extern int sysctl_data_ssthresh;
	 
	 extern int sysctl_post_local;
	+extern int sysctl_mss_adjust;
	 
	 extern unsigned long total_session;
	 extern unsigned long current_session;
	@@ -39,6 +40,13 @@ static struct ctl_table tcp_sysctl_table[] = {
	 		.mode = 0644,
	 		.proc_handler = proc_dointvec
	 	},
	+	{
	+		.procname = "mss_adjust",
	+		.data = &sysctl_mss_adjust,
	+		.maxlen = sizeof(int),
	+		.mode = 0644,
	+		.proc_handler = proc_dointvec
	+	},
	 	{
	 		.procname = "total_session",
	 		.data = &total_session,
```

