---
layout: post
title: "信号量内核源码"
date: 2018-11-14 00:18:00 +0800
comments: false
categories:
- 2018
- 2018~11
- kernel
- kernel~net
tags:
---

https://blog.csdn.net/u012603457/article/details/52971894


之前的一片博客介绍了用于Linux内核同步的自旋锁，即使用自旋锁来保护共享资源，今天介绍另外一种Linux内核同步机制——信号量。信号量在内核中的使用非常广泛，用于对各种共享资源的保护。信号量与自旋锁的实现机制是不一样的，用处也是不一样的。首先，自旋锁和信号量都使用了计数器来表示允许同时访问共享资源的最大进程数，但自旋锁的共享计数值是1，也就是说任意时刻只有一个进程在共享代码区运行；信号量却允许使用大于1的共享计数，即共享资源允许被多个不同的进程同时访问，当然，信号量的计数器也能设为1，这时信号量也称为互斥量。其次，自旋锁用于保护短时间能够完成操作的共享资源，使用期间不允许进程睡眠和进程切换；信号量常用于暂时无法获取的共享资源，如果获取失败则进程进入不可中断的睡眠状态，只能由释放资源的进程来唤醒。最后，自旋锁可以用于中断服务程序之中；信号量不能在中断服务程序中使用，因为中断服务程序是不允许进程睡眠的。关于信号量的基本知识已经讲解完毕，接下来看看信号量在内核里面的实现，本文讲解的内核版本是linux-2.6.24。

### 1 数据结构

```
	struct semaphore {
		atomic_t count;
		int sleepers;
		wait_queue_head_t wait;
	};
```

信号量使用的数据结构是struct semaphore，包含三个数据成员：count是共享计数值、sleepers是等待当前信号量进入睡眠的进程个数、wait是当前信号量的等待队列。

### 2 信号量使用

使用信号量之前要进行初始化，其实只是简单的设置共享计数和等待队列，睡眠进程数一开始是0。本文重点讲解信号量的使用和实现。信号量操作的API：

```
	static inline void down(struct semaphore * sem)//获取信号量，获取失败则进入睡眠状态
	static inline void up(struct semaphore * sem)//释放信号量，并唤醒等待队列中的第一个进程
```
信号量的使用方式如下：

```
	down(sem);
	...临界区...
	up(sem);
```

内核保证正在访问临界区的进程数小于或等于初始化的共享计数值，获取信号量失败的进程将进入不可中断的睡眠状态，在信号量的等待队列中进行等待。当进程释放信号量的时候就会唤醒等待队列中的第一个进程。

### 3 信号量的实现

#### 3.1 down(sem)

首先看函数的定义：

```
	static inline void down(struct semaphore * sem)
	{
		might_sleep();
		__asm__ __volatile__(
			"# atomic down operation\n\t"
			LOCK_PREFIX "decl %0\n\t"	 /* --sem->count */
			"jns 2f\n"
			"\tlea %0,%%eax\n\t"
			"call __down_failed\n"
			"2:"
			:"+m" (sem->count)
			:
			:"memory","ax");
	}
```

这里面包含了一些汇编代码，%0代表sem->count。也就是说先将sem->count减1，LOCK_PREFIX表示执行这条指令时将总线锁住，保证减1操作是原子的。减1之后如果大于或等于0就转到标号2处执行，也就跳过了__down_failed函数直接到函数尾部并返回，成功获取信号量；否则减1之后sem->count小于0则顺序执行后面的__down_failed函数。接下来看__down_failed函数的定义：

```
	ENTRY(__down_failed)
		CFI_STARTPROC
		FRAME
		pushl %edx
		CFI_ADJUST_CFA_OFFSET 4
		CFI_REL_OFFSET edx,0
		pushl %ecx
		CFI_ADJUST_CFA_OFFSET 4
		CFI_REL_OFFSET ecx,0
		call __down
		popl %ecx
		CFI_ADJUST_CFA_OFFSET -4
		CFI_RESTORE ecx
		popl %edx
		CFI_ADJUST_CFA_OFFSET -4
		CFI_RESTORE edx
		ENDFRAME
		ret
		CFI_ENDPROC
		END(__down_failed)
```

pushl和popl是用于保存和恢复寄存器的，CFI前缀的指令用于指令对齐调整。重点在函数__down,下面来看该函数的定义：

```
	fastcall void __sched __down(struct semaphore * sem)
	{
		struct task_struct *tsk = current;
		DECLARE_WAITQUEUE(wait, tsk);
		unsigned long flags;
	
		tsk->state = TASK_UNINTERRUPTIBLE;
		spin_lock_irqsave(&sem->wait.lock, flags);
		add_wait_queue_exclusive_locked(&sem->wait, &wait);
	
		sem->sleepers++;
		for (;;) {
			int sleepers = sem->sleepers;
	
			/*
			 * Add "everybody else" into it. They aren't
			 * playing, because we own the spinlock in
			 * the wait_queue_head.
			 */
			if (!atomic_add_negative(sleepers - 1, &sem->count)) {
				sem->sleepers = 0;
				break;
			}
			sem->sleepers = 1;  /* us - see -1 above */
			spin_unlock_irqrestore(&sem->wait.lock, flags);
	
			schedule();
	
			spin_lock_irqsave(&sem->wait.lock, flags);
			tsk->state = TASK_UNINTERRUPTIBLE;
		}
		remove_wait_queue_locked(&sem->wait, &wait);
		wake_up_locked(&sem->wait);
		spin_unlock_irqrestore(&sem->wait.lock, flags);
		tsk->state = TASK_RUNNING;
	}
```
fastcall表示一种快速调用方式，函数的前两个参数由寄存器ecx和edx来传递，其余参数仍使用堆栈传递。首先将进程设为不可中断睡眠状态，即不能通过信号来唤醒，只能是内核亲自唤醒。同时将进程的TASK_EXCLUSIVE标志设为1，则wake_up()只会唤醒等待队列中的第一个进程。然后将睡眠等待数加1，之后进入for循环。函数atomic_add_negative(sleepers - 1, &sem->count)将相当于sem->count += sleepers-1，然后返回sem->count，通过该函数进行信号量获取情况测试，返回结果为0则获取资源，小于0则没有获取。这段代码使用sleepers和sem->count共同表示当前资源的使用情况。进入for循环后有两种情况，一种是atomic_add_negative执行结果为0，即获取了信号量，此时将sleepers设为0并退出循环，同时唤醒等待队列的第一个进程进行信号量获取测试；另一种是没有获取信号量，将sleepers设为1并运行schedule()进入睡眠，被唤醒之后继续执行for循环进行信号量获取测试。

注意，运行完执行一遍for指令后sleepers的值有两种结果，一种是0，一种是1。如果0则表示有一个进程通过了信号量获取的测试，则atomic_add_negative(sleepers - 1, &sem->count)实际上是将sem->count执行了减1操作，这个操作会在下一个进程进行信号量获取测试的时候执行。如果是1则表示进程没有通过信号领获取的测试，则atomic_add_negative(sleepers - 1, &sem->count)操作不会影响sem->count的值。也就是说，当进程进入__down时，sleepers只会有两个值，一个是0，一个是1。0表示之前的进程获取了信号量，1表示之前的进程没有获取信号量。如果之前进程获取了信号量，执行atomic_add_negative(sleepers - 1, &sem->count)时就会将sem->count的值减1；否则sem->count的值将保持不变。但是这个减1操作延迟到了下一个进程的执行期间，考虑到获取信号量之后进程会唤醒等待队列里的第一个进程，这个减1操作应该会很快就得到执行。

细心地小伙伴可能会注意到，首次获取信号量失败的进程不是会执行sem->sleepers++操作吗，这样不就改变了sem->count的值了吗？仔细回想获取信号量的过程，获取失败的时候会执行sem->count–操作的，因此刚好和sem->sleeper++相互呼应，结果就是不会改变sem->count的结果。即只有进程获取信号量后才会对sem->count进行减1操作，这个操作并不是马上执行，而是后续进程进行信号量获取检测的时候进行的

### 3.2 up(sem)

先看函数定义：

```
	static inline void up(struct semaphore * sem)
	{
		__asm__ __volatile__(
			"# atomic up operation\n\t"
			LOCK_PREFIX "incl %0\n\t"	 /* ++sem->count */
			"jg 1f\n\t"
			"lea %0,%%eax\n\t"
			"call __up_wakeup\n"
			"1:"
			:"+m" (sem->count)
			:
			:"memory","ax");
	}
```

首先将sem->count加1，是原子操作，如果加1后sem->count大于0则说明没有进程在等待信号量资源，无须唤醒队列中进程，直接跳转到标号1处返回；否则运行__up_wakeup唤醒等待队列中的进程。

```
	ENTRY(__up_wakeup)
		CFI_STARTPROC
		FRAME
		pushl %edx
		CFI_ADJUST_CFA_OFFSET 4
		CFI_REL_OFFSET edx,0
		pushl %ecx
		CFI_ADJUST_CFA_OFFSET 4
		CFI_REL_OFFSET ecx,0
		call __up
		popl %ecx
		CFI_ADJUST_CFA_OFFSET -4
		CFI_RESTORE ecx
		popl %edx
		CFI_ADJUST_CFA_OFFSET -4
		CFI_RESTORE edx
		ENDFRAME
		ret
		CFI_ENDPROC
		END(__up_wakeup)
```

同样，我们只关注函数`__up`的定义：
```
	fastcall void __up(struct semaphore *sem)
	{
		wake_up(&sem->wait);
	}
```
可以看到，__up的的工作就是唤醒等待队列中的所有进程，但是由于sem等待队列中的进程 的TASK_EXCLUSIVE标志为 1，因此不会唤醒后续进程了。也就是说up(sem)操作实际上是将sem->count自增1，然后唤醒等待队列中的第一个进程(如果有的话)。
4 小结
信号量作为一种基础的内核同步机制，使用非常广泛。本文基于linux-2.6.24内核版本介绍了信号量使用的数据结构和实现机制，同时介绍了信号量与自旋锁的区别。

