---
layout: post
title: "Linux时间子系统之五：低分辨率定时器的原理和实现"
date: 2017-07-23 16:38:00 +0800
comments: false
categories:
- 2017
- 2017~07
- kernel
- kernel~clock
tags:
---

http://blog.csdn.net/DroidPhone/article/details/8051405

利用定时器，我们可以设定在未来的某一时刻，触发一个特定的事件。所谓低分辨率定时器，是指这种定时器的计时单位基于jiffies值的计数，也就是说，它的精度只有1/HZ，假如你的内核配置的HZ是1000，那意味着系统中的低分辨率定时器的精度就是1ms。早期的内核版本中，内核并不支持高精度定时器，理所当然只能使用这种低分辨率定时器，我们有时候把这种基于HZ的定时器机制成为时间轮：time wheel。虽然后来出现了高分辨率定时器，但它只是内核的一个可选配置项，所以直到目前最新的内核版本，这种低分辨率定时器依然被大量地使用着。

#### 1. 定时器的使用方法

在讨论定时器的实现原理之前，我们先看看如何使用定时器。要在内核编程中使用定时器，首先我们要定义一个time_list结构，该结构在include/Linux/timer.h中定义：

```
	struct timer_list {
		/* 
		 * All fields that change during normal runtime grouped to the 
		 * same cacheline 
		 */
		struct list_head entry;
		unsigned long expires;
		struct tvec_base *base;
	
		void (*function)(unsigned long);
		unsigned long data;
	
		int slack;
			......
	};

	entry  字段用于把一组定时器组成一个链表，至于内核如何对定时器进行分组，我们会在后面进行解释。

	expires  字段指出了该定时器的到期时刻，也就是期望定时器到期时刻的jiffies计数值。

	base  每个cpu拥有一个自己的用于管理定时器的tvec_base结构，该字段指向该定时器所属的cpu所对应tvec_base结构。

	function  字段是一个函数指针，定时器到期时，系统将会调用该回调函数，用于响应该定时器的到期事件。

	data  该字段用于上述回调函数的参数。

	slack  对有些对到期时间精度不太敏感的定时器，到期时刻允许适当地延迟一小段时间，该字段用于计算每次延迟的HZ数。
```

要定义一个timer_list，我们可以使用静态和动态两种办法，静态方法使用DEFINE_TIMER宏：
```
	#define DEFINE_TIMER(_name, _function, _expires, _data)
```

该宏将得到一个名字为_name，并分别用_function,_expires,_data参数填充timer_list的相关字段。

如果要使用动态的方法，则可以自己声明一个timer_list结构，然后手动初始化它的各个字段：

```
	struct timer_list timer;
	......
	init_timer(&timer);
	timer.function = _function;
	timer.expires = _expires;
	timer.data = _data;
```

要激活一个定时器，我们只要调用add_timer即可：

```
	add_timer(&timer);
```

要修改定时器的到期时间，我们只要调用mod_timer即可：

```
	mod_timer(&timer, jiffies+50);
```

要移除一个定时器，我们只要调用del_timer即可：

```
	del_timer(&timer);
```

定时器系统还提供了以下这些API供我们使用：

```
	void add_timer_on(struct timer_list *timer, int cpu);  // 在指定的cpu上添加定时器
	int mod_timer_pending(struct timer_list *timer, unsigned long expires);  //  只有当timer已经处在激活状态时，才修改timer的到期时刻
	int mod_timer_pinned(struct timer_list *timer, unsigned long expires);  //  当
	void set_timer_slack(struct timer_list *time, int slack_hz);  //  设定timer允许的到期时刻的最大延迟，用于对精度不敏感的定时器
	int del_timer_sync(struct timer_list *timer);  //  如果该timer正在被处理中，则等待timer处理完成才移除该timer
```

#### 2. 定时器的软件架构

低分辨率定时器是基于HZ来实现的，也就是说，每个tick周期，都有可能有定时器到期，关于tick如何产生，请参考：Linux时间子系统之四：定时器的引擎：clock_event_device。系统中有可能有成百上千个定时器，难道在每个tick中断中遍历一下所有的定时器，检查它们是否到期？内核当然不会使用这么笨的办法，它使用了一个更聪明的办法：按定时器的到期时间对定时器进行分组。因为目前的多核处理器使用越来越广泛，连智能手机的处理器动不动就是4核心，内核对多核处理器有较好的支持，低分辨率定时器在实现时也充分地考虑了多核处理器的支持和优化。为了较好地利用cache line，也为了避免cpu之间的互锁，内核为多核处理器中的每个cpu单独分配了管理定时器的相关数据结构和资源，每个cpu独立地管理属于自己的定时器。

##### 2.1  定时器的分组

首先，内核为每个cpu定义了一个tvec_base结构指针：

```
	static DEFINE_PER_CPU(struct tvec_base *, tvec_bases) = &boot_tvec_bases;
```

tvec_base结构的定义如下：

```
	struct tvec_base {
		spinlock_t lock;
		struct timer_list *running_timer;
		unsigned long timer_jiffies;
		unsigned long next_timer;
		struct tvec_root tv1;
		struct tvec tv2;
		struct tvec tv3;
		struct tvec tv4;
		struct tvec tv5;
	} ____cacheline_aligned;

running_timer  该字段指向当前cpu正在处理的定时器所对应的timer_list结构。

timer_jiffies  该字段表示当前cpu定时器所经历过的jiffies数，大多数情况下，该值和jiffies计数值相等，当cpu的idle状态连续持续了多个jiffies时间时，当退出idle状态时，jiffies计数值就会大于该字段，在接下来的tick中断后，定时器系统会让该字段的值追赶上jiffies值。

next_timer  该字段指向该cpu下一个即将到期的定时器。

tv1--tv5  这5个字段用于对定时器进行分组，实际上，tv1--tv5都是一个链表数组，其中tv1的数组大小为TVR_SIZE， tv2 tv3 tv4 tv5的数组大小为TVN_SIZE，根据CONFIG_BASE_SMALL配置项的不同，它们有不同的大小：
```

```
	#define TVN_BITS (CONFIG_BASE_SMALL ? 4 : 6)
	#define TVR_BITS (CONFIG_BASE_SMALL ? 6 : 8)
	#define TVN_SIZE (1 << TVN_BITS)
	#define TVR_SIZE (1 << TVR_BITS)
	#define TVN_MASK (TVN_SIZE - 1)
	#define TVR_MASK (TVR_SIZE - 1)
	
	struct tvec {
		struct list_head vec[TVN_SIZE];
	};
	
	struct tvec_root {
		struct list_head vec[TVR_SIZE];
	};
```

默认情况下，没有使能CONFIG_BASE_SMALL，TVR_SIZE的大小是256，TVN_SIZE的大小则是64，当需要节省内存空间时，也可以使能CONFIG_BASE_SMALL，这时TVR_SIZE的大小是64，TVN_SIZE的大小则是16，以下的讨论我都是基于没有使能CONFIG_BASE_SMALL的情况。当有一个新的定时器要加入时，系统根据定时器到期的jiffies值和timer_jiffies字段的差值来决定该定时器被放入tv1至tv5中的哪一个数组中，最终，系统中所有的定时器的组织结构如下图所示：

![](/images/kernel/2017-07-23-4.png)

图 2.1.1  定时器在系统中的组织结构

##### 2.2  定时器的添加

要加入一个新的定时器，我们可以通过api函数add_timer或mod_timer来完成，最终的工作会交由internal_add_timer函数来处理。该函数按以下步骤进行处理：

计算定时器到期时间和所属cpu的tvec_base结构中的timer_jiffies字段的差值，记为idx；

根据idx的值，选择该定时器应该被放到tv1--tv5中的哪一个链表数组中，可以认为tv1-tv5分别占据一个32位数的不同比特位，tv1占据最低的8位，tv2占据紧接着的6位，然后tv3再占位，以此类推，最高的6位分配给tv5。最终的选择规则如下表所示：

```
	链表数组 	idx范围
	tv1 	0-255(2^8)
	tv2 	256--16383(2^14)
	tv3 	16384--1048575(2^20)
	tv4 	1048576--67108863(2^26)
	tv5 	67108864--4294967295(2^32)
```

确定链表数组后，接着要确定把该定时器放入数组中的哪一个链表中，如果时间差idx小于256，按规则要放入tv1中，因为tv1包含了256个链表，所以可以简单地使用timer_list.expires的低8位作为数组的索引下标，把定时器链接到tv1中相应的链表中即可。如果时间差idx的值在256--18383之间，则需要把定时器放入tv2中，同样的，使用timer_list.expires的8--14位作为数组的索引下标，把定时器链接到tv2中相应的链表中,。定时器要加入tv3 tv4 tv5使用同样的原理。经过这样分组后的定时器，在后续的tick事件中，系统可以很方便地定位并取出相应的到期定时器进行处理。以上的讨论都体现在internal_add_timer的代码中：

```
	static void internal_add_timer(struct tvec_base *base, struct timer_list *timer)
	{
		unsigned long expires = timer->expires;
		unsigned long idx = expires - base->timer_jiffies;
		struct list_head *vec;
	
		if (idx < TVR_SIZE) {
			int i = expires & TVR_MASK;
			vec = base->tv1.vec + i;
		} else if (idx < 1 << (TVR_BITS + TVN_BITS)) {
			int i = (expires >> TVR_BITS) & TVN_MASK;
			vec = base->tv2.vec + i;
		} else if (idx < 1 << (TVR_BITS + 2 * TVN_BITS)) {
			int i = (expires >> (TVR_BITS + TVN_BITS)) & TVN_MASK;
			vec = base->tv3.vec + i;
		} else if (idx < 1 << (TVR_BITS + 3 * TVN_BITS)) {
			int i = (expires >> (TVR_BITS + 2 * TVN_BITS)) & TVN_MASK;
			vec = base->tv4.vec + i;
		} else if ((signed long) idx < 0) {
					......
		} else {
					......
			i = (expires >> (TVR_BITS + 3 * TVN_BITS)) & TVN_MASK;
			vec = base->tv5.vec + i;
		}
		list_add_tail(&timer->entry, vec);
	}
```

##### 2.2  定时器的到期处理

经过2.1节的处理后，系统中的定时器按到期时间有规律地放置在tv1--tv5各个链表数组中，其中tv1中放置着在接下来的256个jiffies即将到期的定时器列表，需要注意的是，并不是tv1.vec[0]中放置着马上到期的定时器列表，tv1.vec[1]中放置着将在jiffies+1到期的定时器列表。因为base.timer_jiffies的值一直在随着系统的运行而动态地增加，原则上是每个tick事件会加1，base.timer_jiffies代表者该cpu定时器系统当前时刻，定时器也是动态地加入头256个链表tv1中，按2.1节的讨论，定时器加入tv1中使用的下标索引是定时器到期时间expires的低8位，所以假设当前的base.timer_jiffies值是0x34567826，则马上到期的定时器是在tv1.vec[0x26]中，如果这时候系统加入一个在jiffies值0x34567828到期的定时器，他将会加入到tv1.vec[0x28]中，运行两个tick后，base.timer_jiffies的值会变为0x34567828，很显然，在每次tick事件中，定时器系统只要以base.timer_jiffies的低8位作为索引，取出tv1中相应的链表，里面正好包含了所有在该jiffies值到期的定时器列表。

那什么时候处理tv2--tv5中的定时器？每当base.timer_jiffies的低8位为0值时，这表明base.timer_jiffies的第8-13位有进位发生，这6位正好代表着tv2，这时只要按base.timer_jiffies的第8-13位的值作为下标，移出tv2中对应的定时器链表，然后用internal_add_timer把它们从新加入到定时器系统中来，因为这些定时器一定会在接下来的256个tick期间到期，所以它们肯定会被加入到tv1数组中，这样就完成了tv2往tv1迁移的过程。同样地，当base.timer_jiffies的第8-13位为0时，这表明base.timer_jiffies的第14-19位有进位发生，这6位正好代表着tv3，按base.timer_jiffies的第14-19位的值作为下标，移出tv3中对应的定时器链表，然后用internal_add_timer把它们从新加入到定时器系统中来，显然它们会被加入到tv2中，从而完成tv3到tv2的迁移，tv4，tv5的处理可以以此作类推。具体迁移的代码如下，参数index为事先计算好的高一级tv的需要迁移的数组索引：

```
	static int cascade(struct tvec_base *base, struct tvec *tv, int index)
	{
		/* cascade all the timers from tv up one level */
		struct timer_list *timer, *tmp;
		struct list_head tv_list;
	
		list_replace_init(tv->vec + index, &tv_list);  //  移除需要迁移的链表
	
		/* 
		 * We are removing _all_ timers from the list, so we 
		 * don't have to detach them individually. 
		 */
		list_for_each_entry_safe(timer, tmp, &tv_list, entry) {
			BUG_ON(tbase_get_base(timer->base) != base);
					//  重新加入到定时器系统中，实际上将会迁移到下一级的tv数组中
			internal_add_timer(base, timer);  
		}
	
		return index;
	}
```

每个tick事件到来时，内核会在tick定时中断处理期间激活定时器软中断：TIMER_SOFTIRQ，关于软件中断，请参考另一篇博文：Linux中断（interrupt）子系统之五：软件中断（softIRQ。TIMER_SOFTIRQ的执行函数是`__run_timers`，它实现了本节讨论的逻辑，取出tv1中到期的定时器，执行定时器的回调函数，由此可见，低分辨率定时器的回调函数是执行在软件中断上下文中的，这点在写定时器的回调函数时需要注意。`__run_timers`的代码如下：

```
	static inline void __run_timers(struct tvec_base *base)
	{
		struct timer_list *timer;
	
		spin_lock_irq(&base->lock);
			/* 同步jiffies，在NO_HZ情况下，base->timer_jiffies可能落后不止一个tick  */
		while (time_after_eq(jiffies, base->timer_jiffies)) {  
			struct list_head work_list;
			struct list_head *head = &work_list;
					/*  计算到期定时器链表在tv1中的索引  */
			int index = base->timer_jiffies & TVR_MASK;  
	
			/* 
			 * /*  tv2--tv5定时器列表迁移处理  */
			 */
			if (!index &&
				(!cascade(base, &base->tv2, INDEX(0))) &&              
					(!cascade(base, &base->tv3, INDEX(1))) &&      
						!cascade(base, &base->tv4, INDEX(2)))  
				cascade(base, &base->tv5, INDEX(3));  
					/*  该cpu定时器系统运行时间递增一个tick  */                 
			++base->timer_jiffies;  
					/*  取出到期的定时器链表  */                                       
			list_replace_init(base->tv1.vec + index, &work_list);
					/*  遍历所有的到期定时器  */          
			while (!list_empty(head)) {                                    
				void (*fn)(unsigned long);
				unsigned long data;
	
				timer = list_first_entry(head, struct timer_list,entry);
				fn = timer->function;
				data = timer->data;
	
				timer_stats_account_timer(timer);
	
				base->running_timer = timer;    /*  标记正在处理的定时器  */
				detach_timer(timer, 1);
	
				spin_unlock_irq(&base->lock);
				call_timer_fn(timer, fn, data);  /*  调用定时器的回调函数  */
				spin_lock_irq(&base->lock);
			}
		}
		base->running_timer = NULL;
		spin_unlock_irq(&base->lock);
	}
```

通过上面的讨论，我们可以发现，内核的低分辨率定时器的实现非常精妙，既实现了大量定时器的管理，又实现了快速的O(1)查找到期定时器的能力，利用巧妙的数组结构，使得只需在间隔256个tick时间才处理一次迁移操作，5个数组就好比是5个齿轮，它们随着base->timer_jifffies的增长而不停地转动，每次只需处理第一个齿轮的某一个齿节，低一级的齿轮转动一圈，高一级的齿轮转动一个齿，同时自动把即将到期的定时器迁移到上一个齿轮中，所以低分辨率定时器通常又被叫做时间轮：time wheel。事实上，它的实现是一个很好的空间换时间软件算法。

#### 3. 定时器软件中断

系统初始化时，start_kernel会调用定时器系统的初始化函数init_timers：

```
	void __init init_timers(void)
	{      
		int err = timer_cpu_notify(&timers_nb, (unsigned long)CPU_UP_PREPARE, 
					(void *)(long)smp_processor_id());
	
		init_timer_stats();
	
		BUG_ON(err != NOTIFY_OK);
		register_cpu_notifier(&timers_nb);  /* 注册cpu notify，以便在hotplug时在cpu之间进行定时器的迁移 */
		open_softirq(TIMER_SOFTIRQ, run_timer_softirq);
	}
```

可见，open_softirq把run_timer_softirq注册为TIMER_SOFTIRQ的处理函数，另外，当cpu的每个tick事件到来时，在事件处理中断中，update_process_times会被调用，该函数会进一步调用run_local_timers，run_local_timers会触发TIMER_SOFTIRQ软中断：

```
	void run_local_timers(void)
	{
		hrtimer_run_queues();
		raise_softirq(TIMER_SOFTIRQ);
	}
```

TIMER_SOFTIRQ的处理函数是run_timer_softirq：

```
	static void run_timer_softirq(struct softirq_action *h)
	{
		struct tvec_base *base = __this_cpu_read(tvec_bases);
	
		hrtimer_run_pending();
	
		if (time_after_eq(jiffies, base->timer_jiffies))
			__run_timers(base);
	}
```

好啦，终于看到`__run_timers`函数了，2.2节已经介绍过，正是这个函数完成了对到期定时器的处理工作，也完成了时间轮的不停转动。

