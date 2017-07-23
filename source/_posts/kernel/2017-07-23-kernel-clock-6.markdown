---
layout: post
title: "Linux时间子系统之六：高精度定时器（HRTIMER）的原理和实现"
date: 2017-07-23 16:59:00 +0800
comments: false
categories:
- 2017
- 2017~07
- kernel
- kernel~clock
tags:
---

http://blog.csdn.net/DroidPhone/article/details/8074892

上一篇文章，我介绍了传统的低分辨率定时器的实现原理。而随着内核的不断演进，大牛们已经对这种低分辨率定时器的精度不再满足，而且，硬件也在不断地发展，系统中的定时器硬件的精度也越来越高，这也给高分辨率定时器的出现创造了条件。内核从2.6.16开始加入了高精度定时器架构。在实现方式上，内核的高分辨率定时器的实现代码几乎没有借用低分辨率定时器的数据结构和代码，内核文档给出的解释主要有以下几点：

低分辨率定时器的代码和jiffies的关系太过紧密，并且默认按32位进行设计，并且它的代码已经经过长时间的优化，目前的使用也是没有任何错误，如果硬要基于它来实现高分辨率定时器，势必会打破原有的时间轮概念，并且会引入一大堆#if--#else判断；

虽然大部分时间里，时间轮可以实现O(1)时间复杂度，但是当有进位发生时，不可预测的O(N)定时器级联迁移时间，这对于低分辨率定时器来说问题不大，可是它大大地影响了定时器的精度；

低分辨率定时器几乎是为“超时”而设计的，并为此对它进行了大量的优化，对于这些以“超时”未目的而使用定时器，它们大多数期望在超时到来之前获得正确的结果，然后删除定时器，精确时间并不是它们主要的目的，例如网络通信、设备IO等等。

为此，内核为高精度定时器重新设计了一套软件架构，它可以为我们提供纳秒级的定时精度，以满足对精确时间有迫切需求的应用程序或内核驱动，例如多媒体应用，音频设备的驱动程序等等。以下的讨论用hrtimer(high resolution timer)表示高精度定时器。


#### 1. 如何组织hrtimer？

我们知道，低分辨率定时器使用5个链表数组来组织timer_list结构，形成了著名的时间轮概念，对于高分辨率定时器，我们期望组织它们的数据结构至少具备以下条件：

	稳定而且快速的查找能力；
	快速地插入和删除定时器的能力；
	排序功能；

内核的开发者考察了多种数据结构，例如基数树、哈希表等等，最终他们选择了红黑树（rbtree）来组织hrtimer，红黑树已经以库的形式存在于内核中，并被成功地使用在内存管理子系统和文件系统中，随着系统的运行，hrtimer不停地被创建和销毁，新的hrtimer按顺序被插入到红黑树中，树的最左边的节点就是最快到期的定时器，内核用一个hrtimer结构来表示一个高精度定时器：

```
	struct hrtimer {
		struct timerqueue_node      node;
		ktime_t             _softexpires;
		enum hrtimer_restart        (*function)(struct hrtimer *);
		struct hrtimer_clock_base   *base;
		unsigned long           state;
			......
	};
```

定时器的到期时间用ktime_t来表示，_softexpires字段记录了时间，定时器一旦到期，function字段指定的回调函数会被调用，该函数的返回值为一个枚举值，它决定了该hrtimer是否需要被重新激活：

```
	enum hrtimer_restart {
		HRTIMER_NORESTART,  /* Timer is not restarted */
		HRTIMER_RESTART,    /* Timer must be restarted */
	};
```

state字段用于表示hrtimer当前的状态，有几下几种位组合：

```
	#define HRTIMER_STATE_INACTIVE  0x00  // 定时器未激活
	#define HRTIMER_STATE_ENQUEUED  0x01  // 定时器已经被排入红黑树中
	#define HRTIMER_STATE_CALLBACK  0x02  // 定时器的回调函数正在被调用
	#define HRTIMER_STATE_MIGRATE   0x04  // 定时器正在CPU之间做迁移
```

hrtimer的到期时间可以基于以下几种时间基准系统：

```
	enum  hrtimer_base_type {
		HRTIMER_BASE_MONOTONIC,  // 单调递增的monotonic时间，不包含休眠时间
		HRTIMER_BASE_REALTIME,   // 平常使用的墙上真实时间
		HRTIMER_BASE_BOOTTIME,   // 单调递增的boottime，包含休眠时间
		HRTIMER_MAX_CLOCK_BASES, // 用于后续数组的定义
	};
```

和低分辨率定时器一样，处于效率和上锁的考虑，每个cpu单独管理属于自己的hrtimer，为此，专门定义了一个结构hrtimer_cpu_base：

```
	struct hrtimer_cpu_base {
			......
		struct hrtimer_clock_base   clock_base[HRTIMER_MAX_CLOCK_BASES];
	};
```

其中，clock_base数组为每种时间基准系统都定义了一个hrtimer_clock_base结构，它的定义如下：

```
	struct hrtimer_clock_base {
		struct hrtimer_cpu_base *cpu_base;  // 指向所属cpu的hrtimer_cpu_base结构
			......
		struct timerqueue_head  active;     // 红黑树，包含了所有使用该时间基准系统的hrtimer
		ktime_t         resolution; // 时间基准系统的分辨率
		ktime_t         (*get_time)(void); // 获取该基准系统的时间函数
		ktime_t         softirq_time;// 当用jiffies
		ktime_t         offset;      // 
	};
```

active字段是一个timerqueue_head结构，它实际上是对rbtree的进一步封装：
```
	struct timerqueue_node {
		struct rb_node node;  // 红黑树的节点
		ktime_t expires;      // 该节点代表队hrtimer的到期时间，与hrtimer结构中的_softexpires稍有不同
	};
	
	struct timerqueue_head {
		struct rb_root head;          // 红黑树的根节点
		struct timerqueue_node *next; // 该红黑树中最早到期的节点，也就是最左下的节点
	};
```

timerqueue_head结构在红黑树的基础上，增加了一个next字段，用于保存树中最先到期的定时器节点，实际上就是树的最左下方的节点，有了next字段，当到期事件到来时，系统不必遍历整个红黑树，只要取出next字段对应的节点进行处理即可。timerqueue_node用于表示一个hrtimer节点，它在标准红黑树节点rb_node的基础上增加了expires字段，该字段和hrtimer中的_softexpires字段一起，设定了hrtimer的到期时间的一个范围，hrtimer可以在hrtimer._softexpires至timerqueue_node.expires之间的任何时刻到期，我们也称timerqueue_node.expires为硬过期时间(hard)，意思很明显：到了此时刻，定时器一定会到期，有了这个范围可以选择，定时器系统可以让范围接近的多个定时器在同一时刻同时到期，这种设计可以降低进程频繁地被hrtimer进行唤醒。经过以上的讨论，我们可以得出以下的图示，它表明了每个cpu上的hrtimer是如何被组织在一起的：

![](/images/kernel/2017-07-23-5.png)

图 1.1  每个cpu的hrtimer组织结构

总结一下：

	每个cpu有一个hrtimer_cpu_base结构；
	hrtimer_cpu_base结构管理着3种不同的时间基准系统的hrtimer，分别是：实时时间，启动时间和单调时间；
	每种时间基准系统通过它的active字段（timerqueue_head结构指针），指向它们各自的红黑树；
	红黑树上，按到期时间进行排序，最先到期的hrtimer位于最左下的节点，并被记录在active.next字段中；
	3中时间基准的最先到期时间可能不同，所以，它们之中最先到期的时间被记录在hrtimer_cpu_base的expires_next字段中。


#### 2. hrtimer如何运转

hrtimer的实现需要一定的硬件基础，它的实现依赖于我们前几章介绍的timekeeper和clock_event_device，如果你对timekeeper和clock_event_device不了解请参考以下文章：Linux时间子系统之三：时间的维护者：timekeeper，Linux时间子系统之四：定时器的引擎：clock_event_device。hrtimer系统需要通过timekeeper获取当前的时间，计算与到期时间的差值，并根据该差值，设定该cpu的tick_device（clock_event_device）的下一次的到期时间，时间一到，在clock_event_device的事件回调函数中处理到期的hrtimer。现在你或许有疑问：前面在介绍clock_event_device时，我们知道，每个cpu有自己的tick_device，通常用于周期性地产生进程调度和时间统计的tick事件，这里又说要用tick_device调度hrtimer系统，通常cpu只有一个tick_device，那他们如何协调工作？这个问题也一度困扰着我，如果再加上NO_HZ配置带来tickless特性，你可能会更晕。这里我们先把这个疑问放下，我将在后面的章节中来讨论这个问题，现在我们只要先知道，一旦开启了hrtimer，tick_device所关联的clock_event_device的事件回调函数会被修改为：hrtimer_interrupt，并且会被设置成工作于CLOCK_EVT_MODE_ONESHOT单触发模式。

##### 2.1  添加一个hrtimer

要添加一个hrtimer，系统提供了一些api供我们使用，首先我们需要定义一个hrtimer结构的实例，然后用hrtimer_init函数对它进行初始化，它的原型如下：

```
	void hrtimer_init(struct hrtimer *timer, clockid_t which_clock,
				 enum hrtimer_mode mode);
```

which_clock可以是CLOCK_REALTIME、CLOCK_MONOTONIC、CLOCK_BOOTTIME中的一种，mode则可以是相对时间HRTIMER_MODE_REL，也可以是绝对时间HRTIMER_MODE_ABS。设定回调函数：
```
	timer.function = hr_callback;
```

如果定时器无需指定一个到期范围，可以在设定回调函数后直接使用hrtimer_start激活该定时器：

```
	int hrtimer_start(struct hrtimer *timer, ktime_t tim,
				 const enum hrtimer_mode mode);
```

如果需要指定到期范围，则可以使用hrtimer_start_range_ns激活定时器：

```
	hrtimer_start_range_ns(struct hrtimer *timer, ktime_t tim,
				unsigned long range_ns, const enum hrtimer_mode mode);
```

要取消一个hrtimer，使用hrtimer_cancel：

```
	int hrtimer_cancel(struct hrtimer *timer);
```

以下两个函数用于推后定时器的到期时间：

```
	extern u64
	hrtimer_forward(struct hrtimer *timer, ktime_t now, ktime_t interval);
	
	/* Forward a hrtimer so it expires after the hrtimer's current now */
	static inline u64 hrtimer_forward_now(struct hrtimer *timer,
						  ktime_t interval)
	{
		return hrtimer_forward(timer, timer->base->get_time(), interval);
	}
```

以下几个函数用于获取定时器的当前状态：

```
	static inline int hrtimer_active(const struct hrtimer *timer)
	{
		return timer->state != HRTIMER_STATE_INACTIVE;
	}
	
	static inline int hrtimer_is_queued(struct hrtimer *timer)
	{
		return timer->state & HRTIMER_STATE_ENQUEUED;
	}
	
	static inline int hrtimer_callback_running(struct hrtimer *timer)
	{
		return timer->state & HRTIMER_STATE_CALLBACK;
	}
```

hrtimer_init最终会进入__hrtimer_init函数，该函数的主要目的是初始化hrtimer的base字段，同时初始化作为红黑树的节点的node字段：

```
	static void __hrtimer_init(struct hrtimer *timer, clockid_t clock_id,
				   enum hrtimer_mode mode)
	{
		struct hrtimer_cpu_base *cpu_base;
		int base;
	
		memset(timer, 0, sizeof(struct hrtimer));
	
		cpu_base = &__raw_get_cpu_var(hrtimer_bases);
	
		if (clock_id == CLOCK_REALTIME && mode != HRTIMER_MODE_ABS)
			clock_id = CLOCK_MONOTONIC;
	
		base = hrtimer_clockid_to_base(clock_id);
		timer->base = &cpu_base->clock_base[base];
		timerqueue_init(&timer->node);
			......
	}
```

hrtimer_start和hrtimer_start_range_ns最终会把实际的工作交由__hrtimer_start_range_ns来完成：
```
	int __hrtimer_start_range_ns(struct hrtimer *timer, ktime_t tim,
			unsigned long delta_ns, const enum hrtimer_mode mode,
			int wakeup)
	{
		......        
		/* 取得hrtimer_clock_base指针 */
		base = lock_hrtimer_base(timer, &flags); 
		/* 如果已经在红黑树中，先移除它: */
		ret = remove_hrtimer(timer, base); ......
		/* 如果是相对时间，则需要加上当前时间，因为内部是使用绝对时间 */
		if (mode & HRTIMER_MODE_REL) {
				tim = ktime_add_safe(tim, new_base->get_time());
				......
		} 
		/* 设置到期的时间范围 */
		hrtimer_set_expires_range_ns(timer, tim, delta_ns);
		...... 
		/* 把hrtime按到期时间排序，加入到对应时间基准系统的红黑树中 */
		/* 如果该定时器的是最早到期的，将会返回true */
		leftmost = enqueue_hrtimer(timer, new_base);
		/*
		* Only allow reprogramming if the new base is on this CPU.
		* (it might still be on another CPU if the timer was pending)
		*
		* XXX send_remote_softirq() ? 
		* 定时器比之前的到期时间要早，所以需要重新对tick_device进行编程，重新设定的的到期时间 
		*/
		if (leftmost && new_base->cpu_base == &__get_cpu_var(hrtimer_bases))
				hrtimer_enqueue_reprogram(timer, new_base, wakeup);
		unlock_hrtimer_base(timer, &flags);
		return ret;
	}
```

##### 2.2 hrtimer的到期处理

高精度定时器系统有3个入口可以对到期定时器进行处理，它们分别是：

	没有切换到高精度模式时，在每个jiffie的tick事件中断中进行查询和处理；
	在HRTIMER_SOFTIRQ软中断中进行查询和处理；
	切换到高精度模式后，在每个clock_event_device的到期事件中断中进行查询和处理；

低精度模式  因为系统并不是一开始就会支持高精度模式，而是在系统启动后的某个阶段，等待所有的条件都满足后，才会切换到高精度模式，当系统还没有切换到高精度模式时，所有的高精度定时器运行在低精度模式下，在每个jiffie的tick事件中断中进行到期定时器的查询和处理，显然这时候的精度和低分辨率定时器是一样的（HZ级别）。低精度模式下，每个tick事件中断中，hrtimer_run_queues函数会被调用，由它完成定时器的到期处理。hrtimer_run_queues首先判断目前高精度模式是否已经启用，如果已经切换到了高精度模式，什么也不做，直接返回：

```
	void hrtimer_run_queues(void)
	{
	
		if (hrtimer_hres_active())
			return;
```

如果hrtimer_hres_active返回false，说明目前处于低精度模式下，则继续处理，它用一个for循环遍历各个时间基准系统，查询每个hrtimer_clock_base对应红黑树的左下节点，判断它的时间是否到期，如果到期，通过__run_hrtimer函数，对到期定时器进行处理，包括：调用定时器的回调函数、从红黑树中移除该定时器、根据回调函数的返回值决定是否重新启动该定时器等等：

```
	for (index = 0; index < HRTIMER_MAX_CLOCK_BASES; index++) {
		base = &cpu_base->clock_base[index];
		if (!timerqueue_getnext(&base->active))
			continue;
	
		if (gettime) {
			hrtimer_get_softirq_time(cpu_base);
			gettime = 0;
		}
	
		raw_spin_lock(&cpu_base->lock);
	
		while ((node = timerqueue_getnext(&base->active))) {
			struct hrtimer *timer;
	
			timer = container_of(node, struct hrtimer, node);
			if (base->softirq_time.tv64 <=
					hrtimer_get_expires_tv64(timer))
				break;
	
			__run_hrtimer(timer, &base->softirq_time);
		}
		raw_spin_unlock(&cpu_base->lock);
	}
```

上面的timerqueue_getnext函数返回红黑树中的左下节点，之所以可以在while循环中使用该函数，是因为__run_hrtimer会在移除旧的左下节点时，新的左下节点会被更新到base->active->next字段中，使得循环可以继续执行，直到没有新的到期定时器为止。

高精度模式  切换到高精度模式后，原来给cpu提供tick事件的tick_device（clock_event_device）会被高精度定时器系统接管，它的中断事件回调函数被设置为hrtimer_interrupt，红黑树中最左下的节点的定时器的到期时间被编程到该clock_event_device中，这样每次clock_event_device的中断意味着至少有一个高精度定时器到期。另外，当timekeeper系统中的时间需要修正，或者clock_event_device的到期事件时间被重新编程时，系统会发出HRTIMER_SOFTIRQ软中断，软中断的处理函数run_hrtimer_softirq最终也会调用hrtimer_interrupt函数对到期定时器进行处理，所以在这里我们只要讨论hrtimer_interrupt函数的实现即可。

hrtimer_interrupt函数的前半部分和低精度模式下的hrtimer_run_queues函数完成相同的事情：它用一个for循环遍历各个时间基准系统，查询每个hrtimer_clock_base对应红黑树的左下节点，判断它的时间是否到期，如果到期，通过__run_hrtimer函数，对到期定时器进行处理，所以我们只讨论后半部分，在处理完所有到期定时器后，下一个到期定时器的到期时间保存在变量expires_next中，接下来的工作就是把这个到期时间编程到tick_device中：

```
	void hrtimer_interrupt(struct clock_event_device *dev)
	{
			......
		for (i = 0; i < HRTIMER_MAX_CLOCK_BASES; i++) {
					......
			while ((node = timerqueue_getnext(&base->active))) {
							......
				if (basenow.tv64 < hrtimer_get_softexpires_tv64(timer)) {
					ktime_t expires;
	
					expires = ktime_sub(hrtimer_get_expires(timer),
								base->offset);
					if (expires.tv64 < expires_next.tv64)
						expires_next = expires;
					break;
				}
	
				__run_hrtimer(timer, &basenow);
			}
		}
	
		/* 
		 * Store the new expiry value so the migration code can verify 
		 * against it. 
		 */
		cpu_base->expires_next = expires_next;
		raw_spin_unlock(&cpu_base->lock);
	
		/* Reprogramming necessary ? */
		if (expires_next.tv64 == KTIME_MAX ||
			!tick_program_event(expires_next, 0)) {
			cpu_base->hang_detected = 0;
			return;
		}

	如果这时的tick_program_event返回了非0值，表示过期时间已经在当前时间的前面，这通常由以下原因造成：

		系统正在被调试跟踪，导致时间在走，程序不走；
		定时器的回调函数花了太长的时间；
		系统运行在虚拟机中，而虚拟机被调度导致停止运行；

	为了避免这些情况的发生，接下来系统提供3次机会，重新执行前面的循环，处理到期的定时器：


		raw_spin_lock(&cpu_base->lock);
		now = hrtimer_update_base(cpu_base);
		cpu_base->nr_retries++;
		if (++retries < 3)
			goto retry;

	如果3次循环后还无法完成到期处理，系统不再循环，转为计算本次总循环的时间，
	然后把tick_device的到期时间强制设置为当前时间加上本次的总循环时间，不过推后的时间被限制在100ms以内：


		delta = ktime_sub(now, entry_time);
		if (delta.tv64 > cpu_base->max_hang_time.tv64)
			cpu_base->max_hang_time = delta;
		/* 
		 * Limit it to a sensible value as we enforce a longer 
		 * delay. Give the CPU at least 100ms to catch up. 
		 */
		if (delta.tv64 > 100 * NSEC_PER_MSEC)
			expires_next = ktime_add_ns(now, 100 * NSEC_PER_MSEC);
		else
			expires_next = ktime_add(now, delta);
		tick_program_event(expires_next, 1);
		printk_once(KERN_WARNING "hrtimer: interrupt took %llu ns\n",
				ktime_to_ns(delta));
	}
```

#### 3. 切换到高精度模式

上面提到，尽管内核配置成支持高精度定时器，但并不是一开始就工作于高精度模式，系统在启动的开始阶段，还是按照传统的模式在运行：tick_device按HZ频率定期地产生tick事件，这时的hrtimer工作在低分辨率模式，到期事件在每个tick事件中断中由hrtimer_run_queues函数处理，同时，在低分辨率定时器（时间轮）的软件中断TIMER_SOFTIRQ中，hrtimer_run_pending会被调用，系统在这个函数中判断系统的条件是否满足切换到高精度模式，如果条件满足，则会切换至高分辨率模式，另外提一下，NO_HZ模式也是在该函数中判断并切换。

```
	void hrtimer_run_pending(void)
	{
		if (hrtimer_hres_active())
			return;
			......
		if (tick_check_oneshot_change(!hrtimer_is_hres_enabled()))
			hrtimer_switch_to_hres();
	}
```

因为不管系统是否工作于高精度模式，每个TIMER_SOFTIRQ期间，该函数都会被调用，所以函数一开始先用hrtimer_hres_active判断目前高精度模式是否已经激活，如果已经激活，则说明之前的调用中已经切换了工作模式，不必再次切换，直接返回。hrtimer_hres_active很简单：

```
	DEFINE_PER_CPU(struct hrtimer_cpu_base, hrtimer_bases) = {
			......
	}
	
	static inline int hrtimer_hres_active(void)
	{
		return __this_cpu_read(hrtimer_bases.hres_active);
	}
```

hrtimer_run_pending函数接着通过tick_check_oneshot_change判断系统是否可以切换到高精度模式，

```
	int tick_check_oneshot_change(int allow_nohz)
	{
		struct tick_sched *ts = &__get_cpu_var(tick_cpu_sched);
	
		if (!test_and_clear_bit(0, &ts->check_clocks))
			return 0;
	
		if (ts->nohz_mode != NOHZ_MODE_INACTIVE)
			return 0;
	
		if (!timekeeping_valid_for_hres() || !tick_is_oneshot_available())
			return 0;
	
		if (!allow_nohz)
			return 1;
	
		tick_nohz_switch_to_nohz();
		return 0;
	}
```

函数的一开始先判断check_clock标志的第0位是否被置位，如果没有置位，说明系统中没有注册符合要求的时钟事件设备，函数直接返回，check_clock标志由clocksource和clock_event_device系统的notify系统置位，当系统中有更高精度的clocksource被注册和选择后，或者有更精确的支持CLOCK_EVT_MODE_ONESHOT模式的clock_event_device被注册时，通过它们的notify函数，check_clock标志的第0为会置位。

如果tick_sched结构中的nohz_mode字段不是NOHZ_MODE_INACTIVE，表明系统已经切换到其它模式，直接返回。nohz_mode的取值有3种：

	NOHZ_MODE_INACTIVE    // 未启用NO_HZ模式
	NOHZ_MODE_LOWRES      // 启用NO_HZ模式，hrtimer工作于低精度模式下
	NOHZ_MODE_HIGHRES     // 启用NO_HZ模式，hrtimer工作于高精度模式下

接下来的timerkeeping_valid_for_hres判断timekeeper系统是否支持高精度模式，tick_is_oneshot_available判断tick_device是否支持CLOCK_EVT_MODE_ONESHOT模式。如果都满足要求，则继续往下判断。allow_nohz是函数的参数，为true表明可以切换到NOHZ_MODE_LOWRES 模式，函数将进入tick_nohz_switch_to_nohz，切换至NOHZ_MODE_LOWRES 模式，这里我们传入的allow_nohz是表达式：
```
	(!hrtimer_is_hres_enabled())
```

所以当系统不允许高精度模式时，将会在tick_check_oneshot_change函数内，通过tick_nohz_switch_to_nohz切换至NOHZ_MODE_LOWRES 模式，如果系统允许高精度模式，传入的allow_nohz参数为false，tick_check_oneshot_change函数返回1，回到上面的hrtimer_run_pending函数，hrtimer_switch_to_hres函数将会被调用，已完成切换到NOHZ_MODE_HIGHRES高精度模式。好啦，真正的切换函数找到了，我们看一看它如何切换：

首先，它通过hrtimer_cpu_base中的hres_active字段判断该cpu是否已经切换至高精度模式，如果是则直接返回：

```
	static int hrtimer_switch_to_hres(void)
	{
		int i, cpu = smp_processor_id();
		struct hrtimer_cpu_base *base = &per_cpu(hrtimer_bases, cpu);
		unsigned long flags;
	
		if (base->hres_active)
			return 1;

	接着，通过tick_init_highres函数接管tick_device关联的clock_event_device：

		local_irq_save(flags);
	
		if (tick_init_highres()) {
			local_irq_restore(flags);
			printk(KERN_WARNING "Could not switch to high resolution "
						"mode on CPU %d\n", cpu);
			return 0;
		}
```

tick_init_highres函数把tick_device切换到CLOCK_EVT_FEAT_ONESHOT模式，同时把clock_event_device的回调handler设置为hrtimer_interrupt，这样设置以后，tick_device的中断回调将由hrtimer_interrupt接管，hrtimer_interrupt在上面已经讨论过，它将完成高精度定时器的调度和到期处理。

接着，设置hres_active标志，以表明高精度模式已经切换，然后把3个时间基准系统的resolution字段设为KTIME_HIGH_RES：

```
		base->hres_active = 1;
		for (i = 0; i < HRTIMER_MAX_CLOCK_BASES; i++)
			base->clock_base[i].resolution = KTIME_HIGH_RES;
```

最后，因为tick_device被高精度定时器接管，它将不会再提供原有的tick事件机制，所以需要由高精度定时器系统模拟一个tick事件设备，继续为系统提供tick事件能力，这个工作由tick_setup_sched_timer函数完成。因为刚刚完成切换，tick_device的到期时间并没有被正确地设置为下一个到期定时器的时间，这里使用retrigger_next_event函数，传入参数NULL，使得tick_device立刻产生到期中断，hrtimer_interrupt被调用一次，然后下一个到期的定时器的时间会编程到tick_device中，从而完成了到高精度模式的切换：

```
		tick_setup_sched_timer();
		/* "Retrigger" the interrupt to get things going */
		retrigger_next_event(NULL);
		local_irq_restore(flags);
		return 1;
```

整个切换过程可以用下图表示：

![](/images/kernel/2017-07-23-6.png)

图3.1  低精度模式切换至高精度模式

#### 4. 模拟tick事件

根据上一节的讨论，当系统切换到高精度模式后，tick_device被高精度定时器系统接管，不再定期地产生tick事件，我们知道，到目前的版本为止（V3.4），内核还没有彻底废除jiffies机制，系统还是依赖定期到来的tick事件，供进程调度系统和时间更新等操作，大量存在的低精度定时器也仍然依赖于jiffies的计数，所以，尽管tick_device被接管，高精度定时器系统还是要想办法继续提供定期的tick事件。为了达到这一目的，内核使用了一个取巧的办法：既然高精度模式已经启用，可以定义一个hrtimer，把它的到期时间设定为一个jiffy的时间，当这个hrtimer到期时，在这个hrtimer的到期回调函数中，进行和原来的tick_device同样的操作，然后把该hrtimer的到期时间顺延一个jiffy周期，如此反复循环，完美地模拟了原有tick_device的功能。下面我们看看具体点代码是如何实现的。

在kernel/time/tick-sched.c中，内核定义了一个per_cpu全局变量：tick_cpu_sched，从而为每个cpu提供了一个tick_sched结构， 该结构主要用于管理NO_HZ配置下的tickless处理，因为模拟tick事件与tickless有很强的相关性，所以高精度定时器系统也利用了该结构的以下字段，用于完成模拟tick事件的操作：

```
	struct tick_sched {
		struct hrtimer          sched_timer;
		unsigned long           check_clocks;
		enum tick_nohz_mode     nohz_mode;
			......
	};
```

sched_timer就是要用于模拟tick事件的hrtimer，check_clock上面几节已经讨论过，用于notify系统通知hrtimer系统需要检查是否切换到高精度模式，nohz_mode则用于表示当前的工作模式。

上一节提到，用于切换至高精度模式的函数是hrtimer_switch_to_hres，在它的最后，调用了函数tick_setup_sched_timer，该函数的作用就是设置一个用于模拟tick事件的hrtimer：

```
	void tick_setup_sched_timer(void)
	{
		struct tick_sched *ts = &__get_cpu_var(tick_cpu_sched);
		ktime_t now = ktime_get();
	
		/* 
		 * Emulate tick processing via per-CPU hrtimers: 
		 */
		hrtimer_init(&ts->sched_timer, CLOCK_MONOTONIC, HRTIMER_MODE_ABS);
		ts->sched_timer.function = tick_sched_timer;
	
		/* Get the next period (per cpu) */
		hrtimer_set_expires(&ts->sched_timer, tick_init_jiffy_update());
	
		for (;;) {
			hrtimer_forward(&ts->sched_timer, now, tick_period);
			hrtimer_start_expires(&ts->sched_timer,
						  HRTIMER_MODE_ABS_PINNED);
			/* Check, if the timer was already in the past */
			if (hrtimer_active(&ts->sched_timer))
				break;
			now = ktime_get();
		}
	
	#ifdef CONFIG_NO_HZ
		if (tick_nohz_enabled)
			ts->nohz_mode = NOHZ_MODE_HIGHRES;
	#endif
	}
```

该函数首先初始化该cpu所属的tick_sched结构中sched_timer字段，把该hrtimer的回调函数设置为tick_sched_timer，然后把它的到期时间设定为下一个jiffy时刻，返回前把工作模式设置为NOHZ_MODE_HIGHRES，表明是利用高精度模式实现NO_HZ。

接着我们关注一下hrtimer的回调函数tick_sched_timer，我们知道，系统中的jiffies计数，时间更新等是全局操作，在smp系统中，只有一个cpu负责该工作，所以在tick_sched_timer的一开始，先判断当前cpu是否负责更新jiffies和时间，如果是，则执行更新操作：

```
	static enum hrtimer_restart tick_sched_timer(struct hrtimer *timer)
	{
			......
	
	#ifdef CONFIG_NO_HZ
		if (unlikely(tick_do_timer_cpu == TICK_DO_TIMER_NONE))
			tick_do_timer_cpu = cpu;
	#endif
	
		/* Check, if the jiffies need an update */
		if (tick_do_timer_cpu == cpu)
			tick_do_update_jiffies64(now);

	然后，利用regs指针确保当前是在中断上下文中，然后调用update_process_timer：

		if (regs) {
					   ......
			update_process_times(user_mode(regs));
			......
		}

	最后，把hrtimer的到期时间推进一个tick周期，返回HRTIMER_RESTART表明该hrtimer需要再次启动，以便产生下一个tick事件。

		hrtimer_forward(timer, now, tick_period);
	
		return HRTIMER_RESTART;
	}
```

关于update_process_times，如果你你感兴趣，回看一下本系列关于clock_event_device的那一章：Linux时间子系统之四：定时器的引擎：clock_event_device中的第5小节，对比一下模拟tick事件的hrtimer的回调函数tick_sched_timer和切换前tick_device的回调函数tick_handle_periodic，它们是如此地相像，实际上，它们几乎完成了一样的工作。


