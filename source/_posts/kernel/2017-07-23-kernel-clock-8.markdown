---
layout: post
title: " Linux时间子系统之八：动态时钟框架(CONFIG_NO_HZ、tickless)"
date: 2017-07-23 18:19:00 +0800
comments: false
categories:
- 2017
- 2017~07
- kernel
- kernel~clock
tags:
---

http://blog.csdn.net/DroidPhone/article/details/8112948

在前面章节的讨论中，我们一直基于一个假设：Linux中的时钟事件都是由一个周期时钟提供，不管系统中的clock_event_device是工作于周期触发模式，还是工作于单触发模式，也不管定时器系统是工作于低分辨率模式，还是高精度模式，内核都竭尽所能，用不同的方式提供周期时钟，以产生定期的tick事件，tick事件或者用于全局的时间管理（jiffies和时间的更新），或者用于本地cpu的进程统计、时间轮定时器框架等等。周期性时钟虽然简单有效，但是也带来了一些缺点，尤其在系统的功耗上，因为就算系统目前无事可做，也必须定期地发出时钟事件，激活系统。为此，内核的开发者提出了动态时钟这一概念，我们可以通过内核的配置项CONFIG_NO_HZ来激活特性。有时候这一特性也被叫做tickless，不过还是把它称呼为动态时钟比较合适，因为并不是真的没有tick事件了，只是在系统无事所做的idle阶段，我们可以通过停止周期时钟来达到降低系统功耗的目的，只要有进程处于活动状态，时钟事件依然会被周期性地发出。

在动态时钟正确工作之前，系统需要切换至动态时钟模式，而要切换至动态时钟模式，需要一些前提条件，最主要的一条就是cpu的时钟事件设备必须要支持单触发模式，当条件满足时，系统切换至动态时钟模式，接着，由idle进程决定是否可以停止周期时钟，退出idle进程时则需要恢复周期时钟。

#### 1. 数据结构

在上一章的内容里，我们曾经提到，切换到高精度模式后，高精度定时器系统需要使用一个高精度定时器来模拟传统的周期时钟，其中利用了tick_sched结构中的一些字段，事实上，tick_sched结构也是实现动态时钟的一个重要的数据结构，在smp系统中，内核会为每个cpu都定义一个tick_sched结构，这通过一个percpu全局变量tick_cpu_sched来实现，它在kernel/time/tick-sched.c中定义：

```
	/* 
	 * Per cpu nohz control structure 
	 */
	static DEFINE_PER_CPU(struct tick_sched, tick_cpu_sched);
```

tick_sched结构在include/linux/tick.h中定义，我们看看tick_sched结构的详细定义：
```
	struct tick_sched {
		struct hrtimer          sched_timer;
		unsigned long           check_clocks;
		enum tick_nohz_mode     nohz_mode;
		ktime_t             idle_tick;
		int             inidle;
		int             tick_stopped;
		unsigned long           idle_jiffies;
		unsigned long           idle_calls;
		unsigned long           idle_sleeps;
		int             idle_active;
		ktime_t             idle_entrytime;
		ktime_t             idle_waketime;
		ktime_t             idle_exittime;
		ktime_t             idle_sleeptime;
		ktime_t             iowait_sleeptime;
		ktime_t             sleep_length;
		unsigned long           last_jiffies;
		unsigned long           next_jiffies;
		ktime_t             idle_expires;
		int             do_timer_last;
	};
```

sched_timer  该字段用于在高精度模式下，模拟周期时钟的一个hrtimer，请参看Linux时间子系统之六：高精度定时器（HRTIMER）的原理和实现。

check_clocks  该字段用于实现clock_event_device和clocksource的异步通知机制，帮助系统切换至高精度模式或者是动态时钟模式。

nohz_mode  保存动态时钟的工作模式，基于低分辨率和高精度模式下，动态时钟的实现稍有不同，根据模式它可以是以下的值：

	NOHZ_MODE_INACTIVE  系统动态时钟尚未激活
	NOHZ_MODE_LOWRES  系统工作于低分辨率模式下的动态时钟
	NOHZ_MODE_HIGHRES  系统工作于高精度模式下的动态时钟

idle_tick  该字段用于保存停止周期时钟是的内核时间，当退出idle时要恢复周期时钟，需要使用该时间，以保持系统中时间线（jiffies）的正确性。

tick_stopped  该字段用于表明idle状态的周期时钟已经停止。

idle_jiffies  系统进入idle时的jiffies值，用于信息统计。

idle_calls 系统进入idle的统计次数。

idle_sleeps  系统进入idle且成功停掉周期时钟的次数。

idle_active  表明目前系统是否处于idle状态中。

idle_entrytime  系统进入idle的时刻。

idle_waketime  idle状态被打断的时刻。

idle_exittime  系统退出idle的时刻。

idle_sleeptime  累计各次idle中停止周期时钟的总时间。

sleep_length  本次idle中停止周期时钟的时间。

last_jiffies  系统中最后一次周期时钟的jiffies值。

next_jiffies  预计下一次周期时钟的jiffies。

idle_expires  进入idle后，下一个最先到期的定时器时刻。

我们知道，根据系统目前的工作模式，系统提供周期时钟（tick）的方式会有所不同，当处于低分辨率模式时，由cpu的tick_device提供周期时钟，而当处于高精度模式时，是由一个高精度定时器来提供周期时钟，下面我们分别讨论一下在两种模式下的动态时钟实现方式。

#### 2. 低分辨率下的动态时钟

回看之前一篇文章：Linux时间子系统之四：定时器的引擎：clock_event_device中的关于tick_device一节，不管tick_device的工作模式（周期触发或者是单次触发），tick_device所关联的clock_event_device的事件回调处理函数都是：tick_handle_periodic，不管当前是否处于idle状态，他都会精确地按HZ数来提供周期性的tick事件，这不符合动态时钟的要求，所以，要使动态时钟发挥作用，系统首先要切换至支持动态时钟的工作模式：NOHZ_MODE_LOWRES  。

##### 2.1  切换至动态时钟模式

动态时钟模式的切换过程的前半部分和切换至高精度定时器模式所经过的路径是一样的，请参考：Linux时间子系统之六：高精度定时器（HRTIMER）的原理和实现。这里再简单描述一下过程：系统工作于周期时钟模式，定期地发出tick事件中断，tick事件中断触发定时器软中断：TIMER_SOFTIRQ，执行软中断处理函数run_timer_softirq，run_timer_softirq调用hrtimer_run_pending函数：

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

tick_check_oneshot_change函数的参数决定了现在是要切换至低分辨率动态时钟模式，还是高精度定时器模式，我们现在假设系统不支持高精度定时器模式，hrtimer_is_hres_enabled会直接返回false，对应的tick_check_oneshot_change函数的参数则是true，表明需要切换至动态时钟模式。tick_check_oneshot_change在检查过timekeeper和clock_event_device都具备动态时钟的条件后，通过tick_nohz_switch_to_nohz函数切换至动态时钟模式：

首先，该函数通过tick_switch_to_oneshot函数把tick_device的工作模式设置为单触发模式，并把它的中断事件回调函数置换为tick_nohz_handler，接着把tick_sched结构中的模式字段设置为NOHZ_MODE_LOWRES：

```
	static void tick_nohz_switch_to_nohz(void)
	{
		struct tick_sched *ts = &__get_cpu_var(tick_cpu_sched);
		ktime_t next;
	
		if (!tick_nohz_enabled)
			return;
	
		local_irq_disable();
		if (tick_switch_to_oneshot(tick_nohz_handler)) {
			local_irq_enable();
			return;
		}
	
		ts->nohz_mode = NOHZ_MODE_LOWRES;

	然后，初始化tick_sched结构中的sched_timer定时器，
	通过tick_init_jiffy_update获取下一次tick事件的时间并初始化全局变量last_jiffies_update，
	以便后续可以正确地更新jiffies计数值，最后，把下一次tick事件的时间编程到tick_device中，
	到此，系统完成了到低分辨率动态时钟的切换过程。

		hrtimer_init(&ts->sched_timer, CLOCK_MONOTONIC, HRTIMER_MODE_ABS);
		/* Get the next period */
		next = tick_init_jiffy_update();
	
		for (;;) {
			hrtimer_set_expires(&ts->sched_timer, next);
			if (!tick_program_event(next, 0))
				break;
			next = ktime_add(next, tick_period);
		}
		local_irq_enable();
	}
```

上面的代码中，明明现在没有切换至高精度模式，为什么要初始化tick_sched结构中的高精度定时器？原因并不是要使用它的定时功能，而是想重用hrtimer代码中的hrtimer_forward函数，利用这个函数来计算下一次tick事件的时间。

##### 2.2  低分辨率动态时钟下的事件中断处理函数

上一节提到，当切换至低分辨率动态时钟模式后，tick_device的事件中断处理函数会被设置为tick_nohz_handler，总体来说，它和周期时钟模式的事件处理函数tick_handle_periodic所完成的工作大致类似：更新时间、更新jiffies计数值、调用update_process_time更新进程信息和触发定时器软中断等等，最后重新编程tick_device，使得它在下一个正确的tick时刻再次触发本函数：

```
	static void tick_nohz_handler(struct clock_event_device *dev)
	{
			......
		dev->next_event.tv64 = KTIME_MAX;
	
		if (unlikely(tick_do_timer_cpu == TICK_DO_TIMER_NONE))
			tick_do_timer_cpu = cpu;
	
		/* Check, if the jiffies need an update */
		if (tick_do_timer_cpu == cpu)
			tick_do_update_jiffies64(now);
			......  
		if (ts->tick_stopped) {
			touch_softlockup_watchdog();
			ts->idle_jiffies++;
		}
	
		update_process_times(user_mode(regs));
		profile_tick(CPU_PROFILING);
	
		while (tick_nohz_reprogram(ts, now)) {
			now = ktime_get();
			tick_do_update_jiffies64(now);
		}
	}
```

因为现在工作于动态时钟模式，所以，tick时钟可能在idle进程中被停掉不止一个tick周期，所以当该函数被再次触发时，离上一次触发的时间可能已经不止一个tick周期，tick_nohz_reprogram对tick_device进行编程时必须正确地处理这一情况，它利用了前面所说的hrtimer_forward函数来实现这一特性：
```
	static int tick_nohz_reprogram(struct tick_sched *ts, ktime_t now)
	{
		hrtimer_forward(&ts->sched_timer, now, tick_period);
		return tick_program_event(hrtimer_get_expires(&ts->sched_timer), 0);
	}
```

##### 2.3  动态时钟：停止周期tick时钟事件

开启动态时钟模式后，周期时钟的开启和关闭由idle进程控制，idle进程内最终是一个循环，循环的一开始通过tick_nohz_idle_enter检测是否允许关闭周期时钟若干时间，然后进入低功耗的idle模式，当有中断事件使得cpu退出低功耗idle模式后，判断是否有新的进程被激活从而需要重新调度，如果需要则通过tick_nohz_idle_exit重新启用周期时钟，然后重新进行进程调度，等待下一次idle的发生，我们可以用下图来表示：

![](/images/kernel/2017-07-23-10.png)

图2.3.1  idle进程中的动态时钟处理

停止周期时钟的时机在tick_nohz_idle_enter函数中，它把主要的工作交由tick_nohz_stop_sched_tick函数来完成。内核也不是每次进入tick_nohz_stop_sched_tick都会停止周期时钟，那么什么时候才会停止？我们想一想，这时候既然idle进程在运行，说明系统中的其他进程都在等待某种事件，系统处于无事所做的状态，唯一要处理的就是中断，除了定时器中断，其它的中断我们无法预测它会何时发生，但是我们可以知道最先一个到期的定时器的到期时间，也就是说，在该时间到期前，产生周期时钟是没有必要的，我们可以据此推算出周期时钟可以停止的tick数，然后重新对tick_device进行编程，使得在最早一个定时器到期前都不会产生周期时钟，实际上，tick_nohz_stop_sched_tick还做了一些限制：当下一个定时器的到期时间与当前jiffies值只相差1时，不会停止周期时钟，当定时器的到期时间与当前的jiffies值相差的时间大于timekeeper允许的最大idle时间时，则下一个tick时刻被设置timekeeper允许的最大idle时间，这主要是为了防止太长时间不去更新timekeeper中的系统时间，有可能导致clocksource的溢出问题。tick_nohz_stop_sched_tick函数体看起来很长，实现的也就是上述的逻辑，所以这里就不贴它的代码了，有兴趣的读者可以自行阅读内核的代码：kernel/time/tick-sched.c。

看了动态时钟的停止过程和tick_nohz_handler的实现方式，其实还有一个情况没有处理：当系统进入idle进程后，周期时钟被停止若干个tick周期，当这若干个tick周期到期后，tick事件必然会产生，tick_nohz_handler被触发调用，然后最先到期的定时器被处理。但是在tick_nohz_handler的最后，tick_device一定会被编程为紧跟着的下一个tick周期的时刻被触发，如果刚才的定时器处理后，并没有激活新的进程，我们的期望是周期时钟可以用下一个新的定时器重新计算可以停止的时间，而不是下一个tick时刻，但是tick_nohz_handler却仅仅简单地把tick_device的到期时间设为下一个周期的tick时刻，这导致了周期时钟被恢复，显然这不是我们想要的。为了处理这种情况，内核使用了一点小伎俩，我们知道定时器是在软中断中执行的，所以内核在irq_exit中的软件中断处理完后，加入了一小段代码，kernel/softirq.c ：

```
	void irq_exit(void)
	{
			......
		if (!in_interrupt() && local_softirq_pending())
			invoke_softirq();
	
	#ifdef CONFIG_NO_HZ
		/* Make sure that timer wheel updates are propagated */
		if (idle_cpu(smp_processor_id()) && !in_interrupt() && !need_resched())
			tick_nohz_irq_exit();
	#endif
			......
	}
```

关键的调用是tick_nohz_irq_exit：

```
	void tick_nohz_irq_exit(void)
	{
		struct tick_sched *ts = &__get_cpu_var(tick_cpu_sched);
	
		if (!ts->inidle)
			return;
	
		tick_nohz_stop_sched_tick(ts);
	}
```

tick_nohz_irq_exit再次调用了tick_nohz_stop_sched_tick函数，使得系统有机会再次停止周期时钟若干个tick周期。

##### 2.3 动态时钟：重新开启周期tick时钟事件

回到图2.3.1，当在idle进程中停止周期时钟后，在某一时刻，有新的进程被激活，在重新调度前，tick_nohz_idle_exit会被调用，该函数负责恢复被停止的周期时钟。tick_nohz_idle_exit最终会调用tick_nohz_restart函数，由tick_nohz_restart函数最后完成恢复周期时钟的工作。函数并不复杂：先是把上一次停止周期时钟的时刻设置到tick_sched结构的sched_timer定时器中，然后在通过hrtimer_forward函数把该定时器的到期时刻设置为当前时间的下一个tick时刻，对于高精度模式，启动该定时器即可，对于低分辨率模式，使用该时间对tick_device重新编程，最后通过tick_do_update_jiffies64更新jiffies数值，为了防止此时正在一个tick时刻的边界，可能当前时刻正好刚刚越过了该到期时间，函数使用了一个while循环：

```
	static void tick_nohz_restart(struct tick_sched *ts, ktime_t now)
	{
		hrtimer_cancel(&ts->sched_timer);
		hrtimer_set_expires(&ts->sched_timer, ts->idle_tick);
	
		while (1) {
			/* Forward the time to expire in the future */
			hrtimer_forward(&ts->sched_timer, now, tick_period);
	
			if (ts->nohz_mode == NOHZ_MODE_HIGHRES) {
				hrtimer_start_expires(&ts->sched_timer,
							  HRTIMER_MODE_ABS_PINNED);
				/* Check, if the timer was already in the past */
				if (hrtimer_active(&ts->sched_timer))
					break;
			} else {
				if (!tick_program_event(
					hrtimer_get_expires(&ts->sched_timer), 0))
					break;
			}
			/* Reread time and update jiffies */
			now = ktime_get();
			tick_do_update_jiffies64(now);
		}
	}
```

#### 3. 高精度模式下的动态时钟

高精度模式和低分辨率模式的主要区别是在切换过程中，怎样切换到高精度模式，我已经在上一篇文章中做了说明，切换到高精度模式后，动态时钟的开启和关闭和低分辨率模式下没有太大的区别，也是通过tick_nohz_stop_sched_tick和tick_nohz_restart来控制，在这两个函数中，分别判断了当前的两种模式：

	NOHZ_MODE_HIGHRES
	NOHZ_MODE_LOWRES

如果是NOHZ_MODE_HIGHRES则对tick_sched结构的sched_timer定时器进行设置，如果是NOHZ_MODE_LOWRES，则直接对tick_device进行操作。

#### 4. 动态时钟对中断的影响

在进入和退出中断时，因为动态时钟的关系，中断系统需要作出一些配合。先说中断发生于周期时钟停止期间，如果不做任何处理，中断服务程序中如果要访问jiffies计数值，可能得到一个滞后的jiffies值，因为正常状态下，jiffies值会在恢复周期时钟时正确地更新，所以，为了防止这种情况发生，在进入中断的irq_enter期间，tick_check_idle会被调用：
```
	void tick_check_idle(int cpu)
	{
		tick_check_oneshot_broadcast(cpu);
		tick_check_nohz(cpu);
	}
```
tick_check_nohz函数的最重要的作用就是更新jiffies计数值：
```
	static inline void tick_check_nohz(int cpu)
	{
		struct tick_sched *ts = &per_cpu(tick_cpu_sched, cpu);
		ktime_t now;
	
		if (!ts->idle_active && !ts->tick_stopped)
			return;
		now = ktime_get();
		if (ts->idle_active)
			tick_nohz_stop_idle(cpu, now);
		if (ts->tick_stopped) {
			tick_nohz_update_jiffies(now);
			tick_nohz_kick_tick(cpu, now);
		}
	}
```
另外一种情况是在退出定时器中断时，需要重新评估周期时钟的运行状况，这一点已经在2.3节中做了说明，这里就不在赘述了。

