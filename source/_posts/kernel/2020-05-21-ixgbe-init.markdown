---
layout: post
title: "ixgbe驱动初始化"
date: 2020-05-21 15:33:00 +0800
comments: false
categories:
- 2020
- 2020~05
- kernel
- kernel~10Gb
tags:
---

https://www.cnblogs.com/scottieyuyang/p/5663213.html

#### 首先模块加载insmod ixgbe.ko

```
	module_init(ixgbe_init_module);

	module_init(ixgbe_init_module);
	{
		int ret;
		pr_info("%s - version %s\n", ixgbe_driver_string, ixgbe_driver_version);
		pr_info("%s\n", ixgbe_copyright);

		ixgbe_dbg_init();
	 　　　　ret = pci_register_driver(&ixgbe_driver);
		if (ret) {
			ixgbe_dbg_exit();
			return ret;
		}

	#ifdef CONFIG_IXGBE_DCA
		dca_register_notify(&dca_notifier);
	#endif

		return 0;
	}
```

于是看pci设备的核心结构体

```
	static struct pci_driver ixgbe_driver = {
		.name     = ixgbe_driver_name,
		.id_table = ixgbe_pci_tbl,
		.probe    = ixgbe_probe,
		.remove   = ixgbe_remove,
	#ifdef CONFIG_PM
		.suspend  = ixgbe_suspend,
		.resume   = ixgbe_resume,
	#endif
		.shutdown = ixgbe_shutdown,
		.sriov_configure = ixgbe_pci_sriov_configure,
		.err_handler = &ixgbe_err_handler
	};
```

当设备加载成功后，会执行ixgbe_probe函数

```
	static int ixgbe_probe(struct pci_dev *pdev, const struct pci_device_id *ent)
	{
		/*分配struct net_device *netdev 结构体*/
		netdev = alloc_etherdev_mq(sizeof(struct ixgbe_adapter), indices);

		if (!netdev) {
			err = -ENOMEM;
			goto err_alloc_etherdev;
		}

		SET_NETDEV_DEV(netdev, &pdev->dev);

		/*分配struct ixgbe_adapter *adapter结构体*/
		adapter = netdev_priv(netdev);

		/*分配dev结构体的ops函数指针集合*/
		netdev->netdev_ops = &ixgbe_netdev_ops;

		err = ixgbe_sw_init(adapter);

		err = ixgbe_init_interrupt_scheme(adapter);
		/*设备注册完毕*/<br>
		err = register_netdev(netdev);
	}
```

重点看ixgbe_init_interrupt_scheme(adapter)函数，该函数里面会初始化adapter结构体以及napi相关的东西

```
	int ixgbe_init_interrupt_scheme(struct ixgbe_adapter *adapter)
	{

		err = ixgbe_alloc_q_vectors(adapter);

	}
	static int ixgbe_alloc_q_vectors(struct ixgbe_adapter *adapter)
	{

		if (q_vectors >= (rxr_remaining + txr_remaining)) {
			for (; rxr_remaining; v_idx++) {
				err = ixgbe_alloc_q_vector(adapter, q_vectors, v_idx,
							   0, 0, 1, rxr_idx);

				if (err)
					goto err_out;

				/* update counts and index */
				rxr_remaining--;
				rxr_idx++;
			}
		}
	}
	static int ixgbe_alloc_q_vector(struct ixgbe_adapter *adapter,
					int v_count, int v_idx,
					int txr_count, int txr_idx,
					int rxr_count, int rxr_idx)
	{
		/* setup affinity mask and node */
		if (cpu != -1)
			cpumask_set_cpu(cpu, &q_vector->affinity_mask);
		q_vector->numa_node = node;

	#ifdef CONFIG_IXGBE_DCA
		/* initialize CPU for DCA */
		q_vector->cpu = -1;

	#endif
		/* initialize NAPI */
		netif_napi_add(adapter->netdev, &q_vector->napi,
				   ixgbe_poll, 64);
		napi_hash_add(&q_vector->napi);
	}
```

到此为止，网卡设置初始化完毕　　

其中涉及到如下几个结构体

##### ixgbe_adapter

```
	/* board specific private data structure */
	struct ixgbe_adapter {

		//发送的rings
		struct ixgbe_ring *tx_ring[MAX_TX_QUEUES] ____cacheline_aligned_in_smp;

		//接收的rings
		struct ixgbe_ring *rx_ring[MAX_RX_QUEUES];

		//这个vector里面包含了napi结构
		//应该是跟下面的entries一一对应起来做为是一个中断向量的东西吧
		struct ixgbe_q_vector *q_vector[MAX_Q_VECTORS];

		//这个里面估计是MSIX的多个中断对应的响应接口
		struct msix_entry *msix_entries;
	}

	struct ixgbe_q_vector {
		struct ixgbe_adapter *adapter;
	ifdef CONFIG_IXGBE_DCA
		int cpu;            /* CPU for DCA */
	#endif
		u16 v_idx;              /* index of q_vector within array, also used for
					 * finding the bit in EICR and friends that
					 * represents the vector for this ring */
		u16 itr;                /* Interrupt throttle rate written to EITR */
		struct ixgbe_ring_container rx, tx;

		struct napi_struct napi;/*napi结构体*/
		cpumask_t affinity_mask;
		int numa_node;
		struct rcu_head rcu;    /* to avoid race with update stats on free */
		char name[IFNAMSIZ + 9];

		/* for dynamic allocation of rings associated with this q_vector */
		struct ixgbe_ring ring[0] ____cacheline_internodealigned_in_smp;
	};

	struct napi_struct {
		/* The poll_list must only be managed by the entity which
		 * changes the state of the NAPI_STATE_SCHED bit.  This means
		 * whoever atomically sets that bit can add this napi_struct
		 * to the per-cpu poll_list, and whoever clears that bit
		 * can remove from the list right before clearing the bit.
		 */
		struct list_head    poll_list;

		unsigned long       state;
		int         weight;
		unsigned int        gro_count;
		int         (*poll)(struct napi_struct *, int);//poll的接口实现
	#ifdef CONFIG_NETPOLL
		spinlock_t      poll_lock;
		int         poll_owner;
	#endif
		struct net_device   *dev;
		struct sk_buff      *gro_list;
		struct sk_buff      *skb;
		struct list_head    dev_list;
	};
```


#### 然后当我们ifconfig dev up 时，会执行dev_ops->open函数

```
	static int ixgbe_open(struct net_device *netdev)
	{
		/* allocate transmit descriptors */
		err = ixgbe_setup_all_tx_resources(adapter);
		if (err)
			goto err_setup_tx;

		/* allocate receive descriptors */
		err = ixgbe_setup_all_rx_resources(adapter);
		/*注册中断*/
		err = ixgbe_request_irq(adapter);
	}

	static int ixgbe_request_irq(struct ixgbe_adapter *adapter)
	{
		struct net_device *netdev = adapter->netdev;
		int err;

		if (adapter->flags & IXGBE_FLAG_MSIX_ENABLED)
			err = ixgbe_request_msix_irqs(adapter);
		else if (adapter->flags & IXGBE_FLAG_MSI_ENABLED)
			err = request_irq(adapter->pdev->irq, ixgbe_intr, 0,
					  netdev->name, adapter);
		else
			err = request_irq(adapter->pdev->irq, ixgbe_intr, IRQF_SHARED,
					  netdev->name, adapter);

		if (err)
			e_err(probe, "request_irq failed, Error %d\n", err);

		return err;
	}

	static int ixgbe_request_msix_irqs(struct ixgbe_adapter *adapter)
	{
		for (vector = 0; vector < adapter->num_q_vectors; vector++) {
			struct ixgbe_q_vector *q_vector = adapter->q_vector[vector];
			struct msix_entry *entry = &adapter->msix_entries[vector];

			err = request_irq(entry->vector, &ixgbe_msix_clean_rings, 0,
					  q_vector->name, q_vector);
		}
	}
```

从上面的代码流程可以看出，最终注册的中断处理函数为ixgbe_msix_clean_rings

```
	static irqreturn_t ixgbe_msix_clean_rings(int irq, void *data)
	{
		struct ixgbe_q_vector *q_vector = data;

		/* EIAM disabled interrupts (on this vector) for us */

		if (q_vector->rx.ring || q_vector->tx.ring)
			napi_schedule(&q_vector->napi);

		return IRQ_HANDLED;
	}
```

从上述代码中可以看，该中断处理函数仅仅作为napi的调度者

当数据包到来时，首先唤醒硬中断执行ixgbe_msix_clean_rings函数，最终napi_schedule会调用 `__raise_softirq_irqoff` 去触发一个软中断NET_RX_SOFTIRQ，然后又对应的软中断接口去实现往上的协议栈逻辑


#### 然后看看napi 调度函数都做了些什么工作
```
	static inline void napi_schedule(struct napi_struct *n)
	{
		if (napi_schedule_prep(n))
			__napi_schedule(n);
	}
	void __napi_schedule(struct napi_struct *n)
	{
		unsigned long flags;

		local_irq_save(flags);
		____napi_schedule(this_cpu_ptr(&softnet_data), n);
		local_irq_restore(flags);
	}

	最终可以看出napi调度函数把napi结构体挂到了per cpu的私有数据结构softnet_data上
	struct softnet_data {
		struct Qdisc        *output_queue;
		struct Qdisc        **output_queue_tailp;
		struct list_head    poll_list;
		struct sk_buff      *completion_queue;
		struct sk_buff_head process_queue;

		/* stats */
		unsigned int        processed;
		unsigned int        time_squeeze;
		unsigned int        cpu_collision;
		unsigned int        received_rps;

	#ifdef CONFIG_RPS
		struct softnet_data *rps_ipi_list;

		/* Elements below can be accessed between CPUs for RPS */
		struct call_single_data csd ____cacheline_aligned_in_smp;
		struct softnet_data *rps_ipi_next;
		unsigned int        cpu;
		unsigned int        input_queue_head;
		unsigned int        input_queue_tail;
	#endif
		unsigned int        dropped;
		struct sk_buff_head input_pkt_queue;
		struct napi_struct  backlog;/*napi结构体里面的双向链表中*/
	};
```

NET_RX_SOFTIRQ是收到数据包的软中断信号对应的接口是net_rx_action

NET_TX_SOFTIRQ是发送完数据包后的软中断信号对应的接口是net_tx_action　　


```
	static void net_rx_action(struct softirq_action *h)
	{
		/* 获取每个cpu的数据*/
		struct softnet_data *sd = this_cpu_ptr(&softnet_data);
		while (!list_empty(&sd->poll_list)) {
			struct napi_struct *n;
					n = list_first_entry(&sd->poll_list, struct napi_struct, poll_list);

			if (test_bit(NAPI_STATE_SCHED, &n->state)) {
				work = n->poll(n, weight);
				trace_napi_poll(n);
			}
		}
	}
```

于是就执行到初始化napi结构体中的poll函数，在这里为ixgbe_poll

```
	int ixgbe_poll(struct napi_struct *napi, int budget)
	{
		struct ixgbe_q_vector *q_vector =
					container_of(napi, struct ixgbe_q_vector, napi);
		struct ixgbe_adapter *adapter = q_vector->adapter;
		struct ixgbe_ring *ring;
		int per_ring_budget;
		bool clean_complete = true;

	#ifdef CONFIG_IXGBE_DCA
		if (adapter->flags & IXGBE_FLAG_DCA_ENABLED)
			ixgbe_update_dca(q_vector);
	#endif

		ixgbe_for_each_ring(ring, q_vector->tx)
			clean_complete &= !!ixgbe_clean_tx_irq(q_vector, ring);

		if (!ixgbe_qv_lock_napi(q_vector))
			return budget;

		/* attempt to distribute budget to each queue fairly, but don't allow
		 * the budget to go below 1 because we'll exit polling */
		if (q_vector->rx.count > 1)
			per_ring_budget = max(budget/q_vector->rx.count, 1);
		else
			per_ring_budget = budget;

		ixgbe_for_each_ring(ring, q_vector->rx)
			clean_complete &= (ixgbe_clean_rx_irq(q_vector, ring,
					   per_ring_budget) < per_ring_budget);

		ixgbe_qv_unlock_napi(q_vector);
		/* If all work not completed, return budget and keep polling */
		if (!clean_complete)
			return budget;

		/* all work done, exit the polling mode */
		napi_complete(napi);
		if (adapter->rx_itr_setting & 1)
			ixgbe_set_itr(q_vector);
		if (!test_bit(__IXGBE_DOWN, &adapter->state))
			ixgbe_irq_enable_queues(adapter, ((u64)1 << q_vector->v_idx));

		return 0;
	}

	static int ixgbe_clean_rx_irq(struct ixgbe_q_vector *q_vector,
					   struct ixgbe_ring *rx_ring,
					   const int budget)
	{
		   ixgbe_rx_skb(q_vector, skb);
	}

	static void ixgbe_rx_skb(struct ixgbe_q_vector *q_vector,
				 struct sk_buff *skb)
	{
		if (ixgbe_qv_busy_polling(q_vector))
			netif_receive_skb(skb);
		else
			napi_gro_receive(&q_vector->napi, skb);
	}

	int netif_receive_skb(struct sk_buff *skb)
	{
		int ret;

		net_timestamp_check(netdev_tstamp_prequeue, skb);

		if (skb_defer_rx_timestamp(skb))
			return NET_RX_SUCCESS;

		rcu_read_lock();

	#ifdef CONFIG_RPS
		if (static_key_false(&rps_needed)) {
			struct rps_dev_flow voidflow, *rflow = &voidflow;
			int cpu = get_rps_cpu(skb->dev, skb, &rflow);

			if (cpu >= 0) {
				ret = enqueue_to_backlog(skb, cpu, &rflow->last_qtail);
				rcu_read_unlock();
				return ret;
			}
		}
	#endif
			/*最终协议栈开始收报*/
		ret = __netif_receive_skb(skb);
		rcu_read_unlock();
		return ret;
	}
```

