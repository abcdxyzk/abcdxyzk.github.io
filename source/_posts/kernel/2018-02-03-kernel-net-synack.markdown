---
layout: post
title: "SYN-ACK 重传"
date: 2018-02-03 23:01:00 +0800
comments: false
categories:
- 2018
- 2018~02
- kernel
- kernel~net
tags:
---

### fastopen synack 重传

```
	int tcp_conn_request(struct request_sock_ops *rsk_ops,
				const struct tcp_request_sock_ops *af_ops,
				struct sock *sk, struct sk_buff *skb)
	{
		...
		fastopen = !want_cookie &&
				tcp_try_fastopen(sk, skb, req, &foc, dst);
		...
```

```
	bool tcp_try_fastopen(struct sock *sk, struct sk_buff *skb,
				struct request_sock *req,
				struct tcp_fastopen_cookie *foc,
				struct dst_entry *dst)
	{
		...
		if (tcp_fastopen_create_child(sk, skb, dst, req)) {
		...

```

```
	static bool tcp_fastopen_create_child(struct sock *sk,
					struct sk_buff *skb,
					struct dst_entry *dst,
					struct request_sock *req)
	{
		...
		child = inet_csk(sk)->icsk_af_ops->syn_recv_sock(sk, skb, req, NULL);
		...
		inet_csk_reset_xmit_timer(child, ICSK_TIME_RETRANS,
					TCP_TIMEOUT_INIT, TCP_RTO_MAX);
		...
	}
```

```
	void tcp_retransmit_timer(struct sock *sk)
	{
		struct tcp_sock *tp = tcp_sk(sk);
		struct inet_connection_sock *icsk = inet_csk(sk);

		if (tp->fastopen_rsk) {
			WARN_ON_ONCE(sk->sk_state != TCP_SYN_RECV &&
				sk->sk_state != TCP_FIN_WAIT1);
			tcp_fastopen_synack_timer(sk);
			/* Before we receive ACK to our SYN-ACK don't retransmit
			 * anything else (e.g., data or FIN segments).
			 */
			return;
		}
		...
```

```
	/*
	 *      Timer for Fast Open socket to retransmit SYNACK. Note that the
	 *      sk here is the child socket, not the parent (listener) socket.
	 */
	static void tcp_fastopen_synack_timer(struct sock *sk)
	{
		struct inet_connection_sock *icsk = inet_csk(sk);
		int max_retries = icsk->icsk_syn_retries ? :
			sysctl_tcp_synack_retries + 1; /* add one more retry for fastopen */
		struct request_sock *req;

		req = tcp_sk(sk)->fastopen_rsk;
		req->rsk_ops->syn_ack_timeout(sk, req);

		if (req->num_timeout >= max_retries) {
			tcp_write_err(sk);
			return;
		}
		/* XXX (TFO) - Unlike regular SYN-ACK retransmit, we ignore error
		 * returned from rtx_syn_ack() to make it more persistent like
		 * regular retransmit because if the child socket has been accepted
		 * it's not good to give up too easily.
		 */
		inet_rtx_syn_ack(sk, req);
		req->num_timeout++;
		inet_csk_reset_xmit_timer(sk, ICSK_TIME_RETRANS,
			TCP_TIMEOUT_INIT << req->num_timeout, TCP_RTO_MAX);
	}
```

--------------

http://blog.csdn.net/u011130578/article/details/44954891

#### 1 Why

TCP服务器在收到SYN请求后发送SYN|ACK响应，然后等待对端的ACK到来以完成三次握手。如果没有收到ACK，TCP应该重传SYN|ACK，这个功能由SYN-ACK定时器完成。由于SYN|ACK发送后并没有放入发送队列中，故重传时必须重新构建SYN|ACK报文。

#### 2 When

TCP在发送SYN|ACK响应后设置SYN-ACK定时器：

```
    1465 int tcp_v4_conn_request(struct sock *sk, struct sk_buff *skb)
    1466 {
    ...
    1598     skb_synack = tcp_make_synack(sk, dst, req,
    1599         fastopen_cookie_present(&valid_foc) ? &valid_foc : NULL);　//构建SYN|ACK
    1600
    1601     if (skb_synack) {
    1602         __tcp_v4_send_check(skb_synack, ireq->loc_addr, ireq->rmt_addr);
    1603         skb_set_queue_mapping(skb_synack, skb_get_queue_mapping(skb));
    1604     } else
    1605         goto drop_and_free;
    1606
    1607     if (likely(!do_fastopen)) {
    1608         int err;
    1609         err = ip_build_and_send_pkt(skb_synack, sk, ireq->loc_addr,
    1610              ireq->rmt_addr, ireq->opt);　//发送SYN|ACK
    ...
    1617         /* Add the request_sock to the SYN table */
    1618         inet_csk_reqsk_queue_hash_add(sk, req, TCP_TIMEOUT_INIT);　//将requese sock加入到SYN表中，并设置SYN-ACK定时器
    ...
```

inet_csk_reqsk_queue_hash_add函数：

```
    521 void inet_csk_reqsk_queue_hash_add(struct sock *sk, struct request_sock *req,
    522                    unsigned long timeout)
    523 {
    524     struct inet_connection_sock *icsk = inet_csk(sk);
    525     struct listen_sock *lopt = icsk->icsk_accept_queue.listen_opt;
    526     const u32 h = inet_synq_hash(inet_rsk(req)->rmt_addr, inet_rsk(req)->rmt_port,
    527                      lopt->hash_rnd, lopt->nr_table_entries);
    528
    529     reqsk_queue_hash_req(&icsk->icsk_accept_queue, h, req, timeout);　//将request_sock放入syn_table中并记录超时时间
    530     inet_csk_reqsk_queue_added(sk, timeout);　//设置SYN-ACK定时器
    531 }
```

reqsk_queue_hash_req函数会记录request_sock的超时时间：

```
    262 static inline void reqsk_queue_hash_req(struct request_sock_queue *queue,
    263                     u32 hash, struct request_sock *req,
    264                     unsigned long timeout)
    265 {
    266     struct listen_sock *lopt = queue->listen_opt;
    267
    268     req->expires = jiffies + timeout;　//超时时间
    269     req->num_retrans = 0;
    270     req->num_timeout = 0;
    271     req->sk = NULL;
    272     req->dl_next = lopt->syn_table[hash];
    273
    274     write_lock(&queue->syn_wait_lock);
    275     lopt->syn_table[hash] = req;
    276     write_unlock(&queue->syn_wait_lock);
    277 }
```

inet_csk_reqsk_queue_added函数为整个syn_table设置一个SYN-ACK定时器：

```
    280 static inline void inet_csk_reqsk_queue_added(struct sock *sk,
    281                           const unsigned long timeout)
    282 {
    283     if (reqsk_queue_added(&inet_csk(sk)->icsk_accept_queue) == 0) //如果添加request sock之前syn_table为空
    284         inet_csk_reset_keepalive_timer(sk, timeout);//设置SYN-ACK定时器
    285 }
```

inet_csk_reset_keepalive_timer函数真正设置定时器：

```
    404 void inet_csk_reset_keepalive_timer(struct sock *sk, unsigned long len)
    405 {
    406     sk_reset_timer(sk, &sk->sk_timer, jiffies + len);
    407 }
```

SYN-ACK定时器的超时时间为TCP_TIMEOUT_INIT（1秒）。

#### 3 What

SYN-ACK定时器的结构为sk->sk_timer，其超时函数为tcp_keepalive_timer：

```
    558 static void tcp_keepalive_timer (unsigned long data)
    559 {
    560     struct sock *sk = (struct sock *) data;
    561     struct inet_connection_sock *icsk = inet_csk(sk);
    562     struct tcp_sock *tp = tcp_sk(sk);
    563     u32 elapsed;
    564
    565     /* Only process if socket is not in use. */
    566     bh_lock_sock(sk);
    567     if (sock_owned_by_user(sk)) {
    568         /* Try again later. */
    569         inet_csk_reset_keepalive_timer (sk, HZ/20);
    570         goto out;
    571     }
    572
    573     if (sk->sk_state == TCP_LISTEN) {　//如果是SYN-ACK定时器超时则判断为真
    574         tcp_synack_timer(sk);　//SYN-ACK定时器超时函数
    575         goto out;
    576     }
    ...
```

tcp_synack_timer函数：

```
    534 static void tcp_synack_timer(struct sock *sk)
    535 {
    536     inet_csk_reqsk_queue_prune(sk, TCP_SYNQ_INTERVAL,
    537                    TCP_TIMEOUT_INIT, TCP_RTO_MAX);
    538 }
```

inet_csk_reqsk_queue_prune函数：

```
    570 void inet_csk_reqsk_queue_prune(struct sock *parent,
    571                 const unsigned long interval,
    572                 const unsigned long timeout,
    573                 const unsigned long max_rto)
    574 {
    575     struct inet_connection_sock *icsk = inet_csk(parent);
    576     struct request_sock_queue *queue = &icsk->icsk_accept_queue;
    577     struct listen_sock *lopt = queue->listen_opt;
    578     int max_retries = icsk->icsk_syn_retries ? : sysctl_tcp_synack_retries;
    579     int thresh = max_retries;
    580     unsigned long now = jiffies;
    581     struct request_sock **reqp, *req;
    582     int i, budget;
    583
    584     if (lopt == NULL || lopt->qlen == 0)
    585         return;
    ...
    604     if (lopt->qlen>>(lopt->max_qlen_log-1)) {
    605         int young = (lopt->qlen_young<<1);
    606
    607         while (thresh > 2) {
    608             if (lopt->qlen < young)
    609                 break;
    610             thresh--;
    611             young <<= 1;
    612         }
    613     }
    614
    615     if (queue->rskq_defer_accept)    //需要等待数据到来再唤醒应用进程
    616         max_retries = queue->rskq_defer_accept;
    617
    618     budget = 2 * (lopt->nr_table_entries / (timeout / interval));
    619     i = lopt->clock_hand;
    620
    621     do {　　//遍历SYN table
    622         reqp=&lopt->syn_table[i];
    623         while ((req = *reqp) != NULL) {
    624             if (time_after_eq(now, req->expires)) {　//超时
    625                 int expire = 0, resend = 0;
    626
    627                 syn_ack_recalc(req, thresh, max_retries,
    628                            queue->rskq_defer_accept,
    629                            &expire, &resend);     //计算request sock是否过期以及是否需要重发SYN|ACK
    630                 req->rsk_ops->syn_ack_timeout(parent, req);　//调用tcp_syn_ack_timeout更新信息数据库
    631                 if (!expire &&  //request socket没有超时
    632                     (!resend ||
    633                      !inet_rtx_syn_ack(parent, req) || 　//重传SYN-ACK
    634                      inet_rsk(req)->acked)) {
    635                     unsigned long timeo;
    636
    637                     if (req->num_timeout++ == 0)
    638                         lopt->qlen_young--;
    639                     timeo = min(timeout << req->num_timeout,
    640                             max_rto);
    641                     req->expires = now + timeo;  //更新request_sock超时时间
    642                     reqp = &req->dl_next;
    643                     continue;
    644                 }
    645
    646                 /* Drop this request */
    647                 inet_csk_reqsk_queue_unlink(parent, req, reqp);　
    648                 reqsk_queue_removed(queue, req);
    649                 reqsk_free(req);
    650                 continue;
    651             }
    652             reqp = &req->dl_next;
    653         }
    654
    655         i = (i + 1) & (lopt->nr_table_entries - 1);
    656
    657     } while (--budget > 0);
    658
    659     lopt->clock_hand = i;
    660
    661     if (lopt->qlen)　//syn_table中还有成员
    662         inet_csk_reset_keepalive_timer(parent, interval);　//继续设置定时器，超时
    663 }
```

604-611：当syn_table中剩余空间比较小时，需要减小最大重试次数，以便使旧的request_sock能够更快消亡，从而新的request_sock能够更多的被接受

647-649：将超时的request_sock移出syn_table并释放，即丢弃其对应的连接

631-642：全部满足下列条件就不删除request_sock而只是更新超时时间：  
（1）request_sock没有超时  
（2）下列3个条件之一成立  
     1）不需要重传SYN|ACK  
     2）重传SYN|ACK成功  
     3）应用进程使用TCP_DEFER_ACCEPT socket选项意图使数据到来时listen socket再唤醒进程，当ACK到来但没有数据时  

syn_ack_recalc函数来确定request_sock是否超时以及是否需要重传SYN|ACK：

```
    539 static inline void syn_ack_recalc(struct request_sock *req, const int thresh,
    540                   const int max_retries,
    541                   const u8 rskq_defer_accept,
    542                   int *expire, int *resend)
    543 {
    544     if (!rskq_defer_accept) {    //不需要等待数据到来再调用accept系统调用
    545         *expire = req->num_timeout >= thresh;    //超时次数达到限制则超时
    546         *resend = 1;    //重传SYN|ACK
    547         return;
    548     }
    549     *expire = req->num_timeout >= thresh &&    //超时次数达到限制
    550           (!inet_rsk(req)->acked || req->num_timeout >= max_retries);    //ACK没有到来或超时次数达到最高上限
    551     /*
    552      * Do not resend while waiting for data after ACK,
    553      * start to resend on end of deferring period to give
    554      * last chance for data or ACK to create established socket.
    555      */
    556     *resend = !inet_rsk(req)->acked || //ACK没有到来
    557           req->num_timeout >= rskq_defer_accept - 1;    //超时次数超过或即将达到应用进程的限制，赶快重传SYN|ACK以便给对端最后一个机会建立连接
    558 }
```

综上，SYN|ACK定时器超时时重传SYN|ACK的条件是下列条件全部成立：  
（1）request_sock超时  
（2）request_sock的超时次数达到限制  
（3）下列条件之一成立：  
    1）应用进程没有使用TCP_DEFER_ACCEPT socket选项来延迟accept request_sock的时间  
    2）应用进程使用TCP_DEFER_ACCEPT socket选项设置了超时次数限制，但ACK没有到来或，超时次数达到最高限制且超时次数超过或即将达到应用进程的限制  

SYN|ACK的重传是由inet_rtx_syn_ack函数完成的：

```
    560 int inet_rtx_syn_ack(struct sock *parent, struct request_sock *req)
    561 {
    562     int err = req->rsk_ops->rtx_syn_ack(parent, req);    //指向tcp_v4_rtx_synack或tcp_v6_rtx_synack
    563
    564     if (!err)
    565         req->num_retrans++;
    566     return err;
    567 }
```

tcp_v4_rtx_synack函数：

```
    870 static int tcp_v4_rtx_synack(struct sock *sk, struct request_sock *req)
    871 {
    872     int res = tcp_v4_send_synack(sk, NULL, req, 0, false); //构建并发送SYN-ACK
    873
    874     if (!res)
    875         TCP_INC_STATS_BH(sock_net(sk), TCP_MIB_RETRANSSEGS);
    876     return res;
    877 }
```

