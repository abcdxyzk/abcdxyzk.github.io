---
layout: post
title: "qdisc 的创建过程"
date: 2020-05-22 11:12:00 +0800
comments: false
categories:
- 2020
- 2020~05
- kernel
- kernel~net
tags:
---

http://blog.chinaunix.net/uid-26902809-id-4106161.html


register_netdevice会初始化netdev的Tx调度discipline, 缺省使用noop_qdisc

```
	register_netdevice
	--->dev_init_scheduler


	void dev_init_scheduler(struct net_device *dev)
	{
		dev->qdisc = &noop_qdisc; //缺省为设备配置noop_qdisc
		netdev_for_each_tx_queue(dev, dev_init_scheduler_queue, &noop_qdisc); //缺省为每个队列配置noop_qdisc
		if (dev_ingress_queue(dev))
			dev_init_scheduler_queue(dev, dev_ingress_queue(dev), &noop_qdisc);

		setup_timer(&dev->watchdog_timer, dev_watchdog, (unsigned long)dev);
	}
```

```
	dev_open
	--->__dev_open
	---->dev_activate
	---->attach_default_qdiscs
	---->attach_one_default_qdisc
	为单队列的设备创建pfifo_fast的qdisc

	static void attach_one_default_qdisc(struct net_device *dev,
					struct netdev_queue *dev_queue,
					void *_unused)
	{
		struct Qdisc *qdisc = &noqueue_qdisc;

		if (dev->tx_queue_len) {
			qdisc = qdisc_create_dflt(dev_queue,
					&pfifo_fast_ops, TC_H_ROOT);
			if (!qdisc) {
				netdev_info(dev, "activation failed\n");
				return;
			}
		}
		dev_queue->qdisc_sleeping = qdisc;
	}
```

```
	dev_open
	--->__dev_open
	---->dev_activate
	---->attach_default_qdiscs
	---->qdisc_create_dflt
	为多队列的设备创建mq_qdisc, 创建完mq_qdisc， 接着调用mq_qdisc_ops->mq_init函数为每个队列创建pfifo_fast_ops的qdisc

	struct Qdisc *qdisc_create_dflt(struct netdev_queue *dev_queue,
				struct Qdisc_ops *ops, unsigned int parentid)
	{
		struct Qdisc *sch;

		sch = qdisc_alloc(dev_queue, ops);
		if (IS_ERR(sch))
			goto errout;
		sch->parent = parentid;

		if (!ops->init || ops->init(sch, NULL) == 0)
			return sch;

		qdisc_destroy(sch);
	errout:
		return NULL;
	}
	EXPORT_SYMBOL(qdisc_create_dflt);
```

```
	dev_open
	--->__dev_open
	---->dev_activate
	---->attach_default_qdiscs
	static void attach_default_qdiscs(struct net_device *dev)
	{
		struct netdev_queue *txq;
		struct Qdisc *qdisc;

		txq = netdev_get_tx_queue(dev, 0);

		if (!netif_is_multiqueue(dev) || dev->tx_queue_len == 0) {
			netdev_for_each_tx_queue(dev, attach_one_default_qdisc, NULL);
			dev->qdisc = txq->qdisc_sleeping;
			atomic_inc(&dev->qdisc->refcnt);
		} else {
			qdisc = qdisc_create_dflt(txq, &mq_qdisc_ops, TC_H_ROOT);
			if (qdisc) {
				qdisc->ops->attach(qdisc);
				dev->qdisc = qdisc;
			}
		}
	}
```

```
	dev_open函数会调用dev_activate：
	a. 为单队列的设备创建pfifo_fast的qdisc
	b. 为多队列的设备创建mq_qdisc, 创建完mq_qdisc， 接着调用mq_qdisc_ops->mq_init函数为每个队列创建pfifo_fast_ops的qdisc
	dev_open
	--->__dev_open
	---->dev_activate

	void dev_activate(struct net_device *dev)
	{
		int need_watchdog;

		/* No queueing discipline is attached to device;
		   create default one i.e. pfifo_fast for devices,
		   which need queueing and noqueue_qdisc for
		   virtual interfaces
		*/

		if (dev->qdisc == &noop_qdisc)
			attach_default_qdiscs(dev);

		if (!netif_carrier_ok(dev))
		/* Delay activation until next carrier-on event */
			return;

		need_watchdog = 0;
		netdev_for_each_tx_queue(dev, transition_one_qdisc, &need_watchdog);
		if (dev_ingress_queue(dev))
			transition_one_qdisc(dev, dev_ingress_queue(dev), NULL);

		if (need_watchdog) {
			dev->trans_start = jiffies;
			dev_watchdog_up(dev);
		}
	}
```

