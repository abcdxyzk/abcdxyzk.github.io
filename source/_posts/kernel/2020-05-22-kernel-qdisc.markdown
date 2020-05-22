---
layout: post
title: "qdisc实现分析"
date: 2020-05-22 11:06:00 +0800
comments: false
categories:
- 2020
- 2020~05
- kernel
- kernel~net
tags:
---

```
	tc qdisc show
	echo pfifo > /proc/sys/net/core/default_qdisc
	tc qdisc add dev eth0 root pfifo
	tc qdisc del dev eth0 root
```
https://github.com/liucimin/Learning/blob/master/linux%E7%BD%91%E7%BB%9C%E7%9B%B8%E5%85%B3/Tc%20%E7%BD%91%E5%8D%A1%E5%A4%9A%E9%98%9F%E5%88%97%E6%97%B6%E6%AF%8F%E4%B8%AA%E9%98%9F%E5%88%97%E9%85%8D%E7%BD%AE%E5%85%AC%E5%B9%B3%E9%98%9F%E5%88%97sfq.md

http://man7.org/linux/man-pages/man8/tc-fq_codel.8.html

-------------------

https://blog.csdn.net/one_clouder/article/details/52685249


二层发送中，实现qdisc的主要函数是 `__dev_xmit_skb` 和 net_tx_action，本篇将分析qdisc实现的原理，但是不涉及qdisc内部的算法，仅对框架进行分析。

1、`__dev_xmit_skb` 函数

```
	static inline int __dev_xmit_skb(struct sk_buff *skb, struct Qdisc *q,
					 struct net_device *dev,
					 struct netdev_queue *txq)
	{
		spinlock_t *root_lock = qdisc_lock(q);
		bool contended;
		int rc;
	 
		qdisc_pkt_len_init(skb);
		qdisc_calculate_pkt_len(skb, q);
		/*
		 * Heuristic to force contended enqueues to serialize on a
		 * separate lock before trying to get qdisc main lock.
		 * This permits __QDISC___STATE_RUNNING owner to get the lock more
		 * often and dequeue packets faster.
		 */
		contended = qdisc_is_running(q);	//判断qdisc是否运行
		if (unlikely(contended))
			spin_lock(&q->busylock);
	 
		spin_lock(root_lock);
		if (unlikely(test_bit(__QDISC_STATE_DEACTIVATED, &q->state))) {
			kfree_skb(skb);
			rc = NET_XMIT_DROP;
		} else if ((q->flags & TCQ_F_CAN_BYPASS) && !qdisc_qlen(q) &&	//qisc没有运行，且没有缓存报文，则直接可以发送报文
			   qdisc_run_begin(q)) {
			/*
			 * This is a work-conserving queue; there are no old skbs
			 * waiting to be sent out; and the qdisc is not running -
			 * xmit the skb directly.
			 */
	 
			qdisc_bstats_update(q, skb);
	 
			if (sch_direct_xmit(skb, q, dev, txq, root_lock, true)) {
				if (unlikely(contended)) {
					spin_unlock(&q->busylock);
					contended = false;
				}
				__qdisc_run(q);		//sch_direct_xmit返回为正值，说明qdisc中有报文待发送，尝试发送缓冲区报文
			} else
				qdisc_run_end(q);	//正常发送完成，qdisc停止运行
	 
			rc = NET_XMIT_SUCCESS;
		} else {
			rc = q->enqueue(skb, q) & NET_XMIT_MASK;	//qdisc running或者有缓存报文， 则把报文发动qdisc队列中
			if (qdisc_run_begin(q)) {			//尝试启动qdisc，如果qisc成功启动，则尝试发送报文
				if (unlikely(contended)) {
					spin_unlock(&q->busylock);
					contended = false;
				}
				__qdisc_run(q);		//发送qdisc缓冲队列中的报文
			}
		}
		spin_unlock(root_lock);
		if (unlikely(contended))
			spin_unlock(&q->busylock);
		return rc;
	}
```

2、`__qdisc_run` 函数

```
	void __qdisc_run(struct Qdisc *q)
	{
		int quota = weight_p;
		int packets;
	 
		while (qdisc_restart(q, &packets)) {	//循环发送报文
			/*
			 * Ordered by possible occurrence: Postpone processing if
			 * 1. we've exceeded packet quota
			 * 2. another process needs the CPU;
			 */
			quota -= packets;
			if (quota <= 0 || need_resched()) {	//如果配额或需要调度，则触发软中断后退出
				__netif_schedule(q);
				break;
			}
		}
	 
		qdisc_run_end(q);	//qdisc停止
	}
```

3、qdisc_restart函数

```
	static inline int qdisc_restart(struct Qdisc *q, int *packets)
	{
		struct netdev_queue *txq;
		struct net_device *dev;
		spinlock_t *root_lock;
		struct sk_buff *skb;
		bool validate;
	 
		/* Dequeue packet */
		skb = dequeue_skb(q, &validate, packets);	//从缓存区中得到待发送的报文，因为流量限制原因，就算缓冲区有报文，也可能返回NULL
		if (unlikely(!skb))
			return 0;
	 
		root_lock = qdisc_lock(q);
		dev = qdisc_dev(q);
		txq = skb_get_tx_queue(dev, skb);
	 
		return sch_direct_xmit(skb, q, dev, txq, root_lock, validate);	//发送报文
	}
```

4、dequeue_skb函数

```
	static struct sk_buff *dequeue_skb(struct Qdisc *q, bool *validate,
					   int *packets)
	{
		struct sk_buff *skb = q->gso_skb;
		const struct netdev_queue *txq = q->dev_queue;
	 
		*packets = 1;
		*validate = true;
		if (unlikely(skb)) {
			/* check the reason of requeuing without tx lock first */
			txq = skb_get_tx_queue(txq->dev, skb);
			if (!netif_xmit_frozen_or_stopped(txq)) {
				q->gso_skb = NULL;
				q->q.qlen--;
			} else
				skb = NULL;
			/* skb in gso_skb were already validated */
			*validate = false;
		} else {
			if (!(q->flags & TCQ_F_ONETXQUEUE) ||
				!netif_xmit_frozen_or_stopped(txq)) {
				skb = q->dequeue(q);			//调用qdisc的dequeue函数获取skb
				if (skb && qdisc_may_bulk(q))<span style="white-space:pre">		</span>//如果还能继续获取skb，则一次性获取多个skb
					try_bulk_dequeue_skb(q, skb, txq, packets);
			}
		}
		return skb;
	}
```

net_tx_action

net_tx_action为报文发送软中断，在处理报文发送软中断时，尝试该CPU softnet_data上所有qdisc发送报文。

```
	static void net_tx_action(struct softirq_action *h)
	{
		struct softnet_data *sd = this_cpu_ptr(&softnet_data);
	 
		if (sd->completion_queue) {
			struct sk_buff *clist;
	 
			local_irq_disable();
			clist = sd->completion_queue;
			sd->completion_queue = NULL;
			local_irq_enable();
	 
			while (clist) {
				struct sk_buff *skb = clist;
				clist = clist->next;
	 
				WARN_ON(atomic_read(&skb->users));
				if (likely(get_kfree_skb_cb(skb)->reason == SKB_REASON_CONSUMED))
					trace_consume_skb(skb);
				else
					trace_kfree_skb(skb, net_tx_action);
				__kfree_skb(skb);
			}
		}
	 
		if (sd->output_queue) {
			struct Qdisc *head;
	 
			local_irq_disable();
			head = sd->output_queue;
			sd->output_queue = NULL;
			sd->output_queue_tailp = &sd->output_queue;
			local_irq_enable();
	 
			while (head) {
				struct Qdisc *q = head;
				spinlock_t *root_lock;
	 
				head = head->next_sched;
	 
				root_lock = qdisc_lock(q);
				if (spin_trylock(root_lock)) {
					smp_mb__before_atomic();
					clear_bit(__QDISC_STATE_SCHED,
						  &q->state);
					qdisc_run(q);		    //尝试启动qdisc发送报文
					spin_unlock(root_lock);
				} else {
					if (!test_bit(__QDISC_STATE_DEACTIVATED,
							  &q->state)) {
						__netif_reschedule(q);
					} else {
						smp_mb__before_atomic();
						clear_bit(__QDISC_STATE_SCHED,
							  &q->state);
					}
				}
			}
		}
	}
```

