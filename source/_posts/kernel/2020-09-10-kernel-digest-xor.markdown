---
layout: post
title: "skb 数据异或"
date: 2020-09-10 04:21:00 +0800
comments: false
categories:
- 2020
- 2020~09
- kernel
- kernel~net
tags:
---

skb 数据异或，可以改进成单独的option

```
	diff --git a/run.sh b/run.sh
	index 138e664..0eb5286 100755
	--- a/run.sh
	+++ b/run.sh
	@@ -19,3 +19,4 @@ echo 10000 > /proc/sys/net/tcp/digest/digest_err_retry
	 echo 0 > /proc/sys/net/ipv4/tcp_timestamps # for mptcp+digest
	 
	 echo 0 > /proc/sys/net/tcp/mss_adjust
	+echo 0 > /proc/sys/net/tcp/digest/digest_xor
	diff --git a/src/4.15.18/tcp_ipv4.c b/src/4.15.18/tcp_ipv4.c
	index b1b5c9d..b94ba6e 100644
	--- a/src/4.15.18/tcp_ipv4.c
	+++ b/src/4.15.18/tcp_ipv4.c
	@@ -1848,6 +1848,12 @@ int tcp_v4_rcv(struct sk_buff *skb)
	 	th = (const struct tcphdr *)skb->data;
	 	iph = ip_hdr(skb);
	 
	+	if (digest_xor && digest_skb_check(NULL, NULL, skb, 0) != 0) {
	+		int idx = 0;
	+		skb->ip_summed = CHECKSUM_UNNECESSARY;
	+		skb_change_bits(skb, th->doff * 4, skb->len - th->doff * 4, &idx);
	+	}
	+
	 lookup:
	 	sk = __inet_lookup_skb(&tcp_hashinfo, skb, __tcp_hdrlen(th), th->source,
	 			       th->dest, sdif, &refcounted);
	diff --git a/src/4.15.18/tcp_ipv6.c b/src/4.15.18/tcp_ipv6.c
	index 3e27967..e975356 100644
	--- a/src/4.15.18/tcp_ipv6.c
	+++ b/src/4.15.18/tcp_ipv6.c
	@@ -1619,6 +1619,12 @@ int tcp_v6_rcv(struct sk_buff *skb)
	 	th = (const struct tcphdr *)skb->data;
	 	hdr = ipv6_hdr(skb);
	 
	+	if (digest_xor && digest_skb_check(NULL, NULL, skb, 0) != 0) {
	+		int idx = 0;
	+		skb->ip_summed = CHECKSUM_UNNECESSARY;
	+		skb_change_bits(skb, th->doff * 4, skb->len - th->doff * 4, &idx);
	+	}
	+
	 lookup:
	 	sk = __inet6_lookup_skb(&tcp_hashinfo, skb, __tcp_hdrlen(th),
	 				th->source, th->dest, inet6_iif(skb), sdif,
	diff --git a/src/4.15.18/tcp_output.c b/src/4.15.18/tcp_output.c
	index dab8703..f766ff4 100644
	--- a/src/4.15.18/tcp_output.c
	+++ b/src/4.15.18/tcp_output.c
	@@ -1118,6 +1118,9 @@ static int __tcp_transmit_skb(struct sock *sk, struct sk_buff *skb,
	 		oskb = skb;
	 
	 		tcp_skb_tsorted_save(oskb) {
	+			if (digest_xor)
	+				skb = skb_copy(oskb, gfp_mask);
	+			else
	 			if (unlikely(skb_cloned(oskb)))
	 				skb = pskb_copy(oskb, gfp_mask);
	 			else
	@@ -1264,6 +1267,13 @@ static int __tcp_transmit_skb(struct sock *sk, struct sk_buff *skb,
	 		sk_nocaps_add(sk, NETIF_F_GSO_MASK);
	 
	 		merge = !(tcb->tcp_flags & TCPHDR_SYN);
	+
	+		if (digest_xor) {
	+			int idx = 0;
	+			skb_change_bits(skb, tcp_header_size, skb->len - tcp_header_size, &idx);
	+			skb->ip_summed = CHECKSUM_NONE;
	+			skb->csum = skb_checksum(skb, tcp_header_size, skb->len - tcp_header_size, 0);
	+		}
	 	}
	 
	 	tcp_options_write((__be32 *)(th + 1), tp, &opts, skb, merge);
	@@ -3122,6 +3132,9 @@ int __tcp_retransmit_skb(struct sock *sk, struct sk_buff *skb, int segs)
	 		struct sk_buff *nskb;
	 
	 		tcp_skb_tsorted_save(skb) {
	+			if (digest_xor)
	+				nskb = skb_copy(skb, GFP_ATOMIC);
	+			else
	 			nskb = __pskb_copy(skb, MAX_TCP_HEADER, GFP_ATOMIC);
	 			err = nskb ? tcp_transmit_skb(sk, nskb, 0, GFP_ATOMIC) :
	 				     -ENOBUFS;
	diff --git a/src/digest_core.c b/src/digest_core.c
	index 54af869..50840b1 100644
	--- a/src/digest_core.c
	+++ b/src/digest_core.c
	@@ -419,9 +419,106 @@ out:
	 	return 0;
	 }
	 
	+
	+int digest_xor = 0;
	+unsigned char MY_XOR[65536];
	+
	+int __skb_change_bits(unsigned char *from, int len, int *idx)
	+{
	+	int i;
	+	for (i = 0; i < len; i ++) {
	+		*(from + i) ^= MY_XOR[*idx];
	+		*idx = *idx + 1;
	+	}
	+	return 0;
	+}
	+
	+int skb_change_bits(struct sk_buff *skb, int offset, int len, int *idx)
	+{
	+	int start = skb_headlen(skb);
	+	struct sk_buff *frag_iter;
	+	int i, copy;
	+
	+	if (offset > (int)skb->len - len)
	+		goto fault;
	+
	+	/* Copy header. */
	+	if ((copy = start - offset) > 0) {
	+		if (copy > len)
	+			copy = len;
	+		// skb_copy_from_linear_data_offset(skb, offset, to, copy);
	+		__skb_change_bits(skb->data + offset, copy, idx);
	+		if ((len -= copy) == 0)
	+			return 0;
	+		offset += copy;
	+	}
	+
	+	for (i = 0; i < skb_shinfo(skb)->nr_frags; i++) {
	+		int end;
	+		skb_frag_t *f = &skb_shinfo(skb)->frags[i];
	+
	+		WARN_ON(start > offset + len);
	+
	+		end = start + skb_frag_size(f);
	+		if ((copy = end - offset) > 0) {
	+			u32 p_off, p_len, copied;
	+			struct page *p;
	+			u8 *vaddr;
	+
	+			if (copy > len)
	+				copy = len;
	+
	+#if LINUX_VERSION_CODE > KERNEL_VERSION(4, 0, 0)
	+			skb_frag_foreach_page(f,
	+					      f->page_offset + offset - start,
	+					      copy, p, p_off, p_len, copied) {
	+				vaddr = kmap_atomic(p);
	+				//memcpy(to + copied, vaddr + p_off, p_len);
	+				__skb_change_bits(vaddr + p_off, p_len, idx);
	+				kunmap_atomic(vaddr);
	+			}
	+#else
	+			vaddr = kmap_atomic(skb_frag_page(f));
	+			__skb_change_bits(vaddr + f->page_offset + offset - start, copy, idx);
	+			kunmap_atomic(vaddr);
	+#endif
	+
	+			if ((len -= copy) == 0)
	+				return 0;
	+			offset += copy;
	+		}
	+		start = end;
	+	}
	+
	+	skb_walk_frags(skb, frag_iter) {
	+		int end;
	+
	+		WARN_ON(start > offset + len);
	+
	+		end = start + frag_iter->len;
	+		if ((copy = end - offset) > 0) {
	+			if (copy > len)
	+				copy = len;
	+			if (skb_change_bits(frag_iter, offset - start, copy, idx))
	+				goto fault;
	+			if ((len -= copy) == 0)
	+				return 0;
	+			offset += copy;
	+		}
	+		start = end;
	+	}
	+
	+	if (!len)
	+		return 0;
	+
	+fault:
	+	return -EFAULT;
	+}
	+
	+
	 int digest_init(void)
	 {
	 	int ret;
	+	int i;
	+	unsigned long int next = 1;
	+	for (i = 0; i < 65536; i ++) {
	+		next = next * 2 + 1; // TODO
	+		MY_XOR[i] = next % 251;
	+	}
	 
	 	ret = digest_trace_init();
	 	if (ret) {
	diff --git a/src/digest_core.h b/src/digest_core.h
	index 007628c..3ba8c80 100644
	--- a/src/digest_core.h
	+++ b/src/digest_core.h
	@@ -79,6 +79,8 @@ extern atomic_t digest_trace_head_num;
	 extern atomic_t digest_trace_free_num;
	 extern int digest_trace_log_total;
	 
	+extern int digest_xor;
	+
	 extern atomic64_t digest_current, digest_total1, digest_total2, digest_drop_total;
	 extern atomic64_t digest_drop_connect;
	 
	diff --git a/src/digest_sysctl.c b/src/digest_sysctl.c
	index 27adfa3..3bfe0ae 100644
	--- a/src/digest_sysctl.c
	+++ b/src/digest_sysctl.c
	@@ -478,6 +478,13 @@ static struct ctl_table digest_sysctl_table[] = {
	 		.mode = 0644,
	 		.proc_handler = digest_trace_show_handler,
	 	},
	+	{
	+		.procname = "digest_xor",
	+		.data = &digest_xor,
	+		.maxlen = sizeof(int),
	+		.mode = 0644,
	+		.proc_handler = proc_dointvec
	+	},
	 	{}
	 };
``` 
