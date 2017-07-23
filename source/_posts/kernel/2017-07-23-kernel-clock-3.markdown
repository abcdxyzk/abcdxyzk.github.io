---
layout: post
title: "Linux时间子系统之三：时间的维护者：timekeeper"
date: 2017-07-23 13:46:00 +0800
comments: false
categories:
- 2017
- 2017~07
- kernel
- kernel~clock
tags:
---

http://blog.csdn.net/droidphone/article/details/7989566

本系列文章的前两节讨论了用于计时的时钟源：clocksource，以及内核内部时间的一些表示方法，但是对于真实的用户来说，我们感知的是真实世界的真实时间，也就是所谓的墙上时间，clocksource只能提供一个按给定频率不停递增的周期计数，如何把它和真实的墙上时间相关联？本节的内容正是要讨论这一点。

#### 1. 时间的种类

内核管理着多种时间，它们分别是：

```
	RTC时间
	wall time：墙上时间
	monotonic time
	raw monotonic time
	boot time：总启动时间
```

RTC时间  在PC中，RTC时间又叫CMOS时间，它通常由一个专门的计时硬件来实现，软件可以读取该硬件来获得年月日、时分秒等时间信息，而在嵌入式系统中，有使用专门的RTC芯片，也有直接把RTC集成到Soc芯片中，读取Soc中的某个寄存器即可获取当前时间信息。一般来说，RTC是一种可持续计时的，也就是说，不管系统是否上电，RTC中的时间信息都不会丢失，计时会一直持续进行，硬件上通常使用一个后备电池对RTC硬件进行单独的供电。因为RTC硬件的多样性，开发者需要为每种RTC时钟硬件提供相应的驱动程序，内核和用户空间通过驱动程序访问RTC硬件来获取或设置时间信息。

xtime  xtime和RTC时间一样，都是人们日常所使用的墙上时间，只是RTC时间的精度通常比较低，大多数情况下只能达到毫秒级别的精度，如果是使用外部的RTC芯片，访问速度也比较慢，为此，内核维护了另外一个wall time时间：xtime，取决于用于对xtime计时的clocksource，它的精度甚至可以达到纳秒级别，因为xtime实际上是一个内存中的变量，它的访问速度非常快，内核大部分时间都是使用xtime来获得当前时间信息。xtime记录的是自1970年1月1日24时到当前时刻所经历的纳秒数。

monotonic time  该时间自系统开机后就一直单调地增加，它不像xtime可以因用户的调整时间而产生跳变，不过该时间不计算系统休眠的时间，也就是说，系统休眠时，monotoic时间不会递增。

raw monotonic time  该时间与monotonic时间类似，也是单调递增的时间，唯一的不同是：raw monotonic time“更纯净”，他不会受到NTP时间调整的影响，它代表着系统独立时钟硬件对时间的统计。

boot time  与monotonic时间相同，不过会累加上系统休眠的时间，它代表着系统上电后的总时间。

```
	时间种 类     精度（统计单位）     访问速度     累计休眠时间     受NTP调整的影响
	RTC           低                   慢           Yes              Yes
	xtime         高                   快           Yes              Yes
	monotonic     高                   快           No               Yes
	raw monotonic 高                   快           No               No
	boot time     高                   快           Yes              Yes
```

#### 2. struct timekeeper

内核用timekeeper结构来组织与时间相关的数据，它的定义如下：

```
	struct timekeeper {  
		struct clocksource *clock;    /* Current clocksource used for timekeeping. */  
		u32 mult;    /* NTP adjusted clock multiplier */  
		int shift;  /* The shift value of the current clocksource. */  
		cycle_t cycle_interval; /* Number of clock cycles in one NTP interval. */  
		u64 xtime_interval; /* Number of clock shifted nano seconds in one NTP interval. */  
		s64 xtime_remainder;    /* shifted nano seconds left over when rounding cycle_interval */  
		u32 raw_interval;   /* Raw nano seconds accumulated per NTP interval. */  
	  
		u64 xtime_nsec; /* Clock shifted nano seconds remainder not stored in xtime.tv_nsec. */  
		/* Difference between accumulated time and NTP time in ntp 
		 * shifted nano seconds. */  
		s64 ntp_error;  
		/* Shift conversion between clock shifted nano seconds and 
		 * ntp shifted nano seconds. */  
		int ntp_error_shift;  
	  
		struct timespec xtime;  /* The current time */  
	  
		struct timespec wall_to_monotonic;  
		struct timespec total_sleep_time;   /* time spent in suspend */  
		struct timespec raw_time;   /* The raw monotonic time for the CLOCK_MONOTONIC_RAW posix clock. */  
	  
		ktime_t offs_real;  /* Offset clock monotonic -> clock realtime */  
	  
		ktime_t offs_boot;  /* Offset clock monotonic -> clock boottime */  
	  
		seqlock_t lock; /* Seqlock for all timekeeper values */  
	};  
```

其中的xtime字段就是上面所说的墙上时间，它是一个timespec结构的变量，它记录了自1970年1月1日以来所经过的时间，因为是timespec结构，所以它的精度可以达到纳秒级，当然那要取决于系统的硬件是否支持这一精度。

内核除了用xtime表示墙上的真实时间外，还维护了另外一个时间：monotonic time，可以把它理解为自系统启动以来所经过的时间，该时间只能单调递增，可以理解为xtime虽然正常情况下也是递增的，但是毕竟用户可以主动向前或向后调整墙上时间，从而修改xtime值。但是monotonic时间不可以往后退，系统启动后只能不断递增。奇怪的是，内核并没有直接定义一个这样的变量来记录monotonic时间，而是定义了一个变量wall_to_monotonic，记录了墙上时间和monotonic时间之间的偏移量，当需要获得monotonic时间时，把xtime和wall_to_monotonic相加即可，因为默认启动时monotonic时间为0，所以实际上wall_to_monotonic的值是一个负数，它和xtime同一时间被初始化，请参考timekeeping_init函数。

计算monotonic时间要去除系统休眠期间花费的时间，内核用total_sleep_time记录休眠的时间，每次休眠醒来后重新累加该时间，并调整wall_to_monotonic的值，使其在系统休眠醒来后，monotonic时间不会发生跳变。因为wall_to_monotonic值被调整。所以如果想获取boot time，需要加入该变量的值：

```
	void get_monotonic_boottime(struct timespec *ts)  
	{  
			......  
		do {  
			seq = read_seqbegin(&timekeeper.lock);  
			*ts = timekeeper.xtime;  
			tomono = timekeeper.wall_to_monotonic;  
			<span style="color:#ff0000;">sleep = timekeeper.total_sleep_time;</span>  
			nsecs = timekeeping_get_ns();  
	  
		} while (read_seqretry(&timekeeper.lock, seq));  
	  
		set_normalized_timespec(ts, ts->tv_sec + tomono.tv_sec + sleep.tv_sec,  
				ts->tv_nsec + tomono.tv_nsec + sleep.tv_nsec + nsecs);  
	}  
```

raw_time字段用来表示真正的硬件时间，也就是上面所说的raw monotonic time，它不受时间调整的影响，monotonic时间虽然也不受settimeofday的影响，但会受到ntp调整的影响，但是raw_time不受ntp的影响，他真的就是开完机后就单调地递增。xtime、monotonic-time和raw_time可以通过用户空间的clock_gettime函数获得，对应的ID参数分别是 CLOCK_REALTIME、CLOCK_MONOTONIC、CLOCK_MONOTONIC_RAW。

clock字段则指向了目前timekeeper所使用的时钟源，xtime，monotonic time和raw time都是基于该时钟源进行计时操作，当有新的精度更高的时钟源被注册时，通过timekeeping_notify函数，change_clocksource函数将会被调用，timekeeper.clock字段将会被更新，指向新的clocksource。

早期的内核版本中，xtime、wall_to_monotonic、raw_time其实是定义为全局静态变量，到我目前的版本（V3.4.10），这几个变量被移入到了timekeeper结构中，现在只需维护一个timekeeper全局静态变量即可：

```
	static struct timekeeper timekeeper;  
```

#### 3. timekeeper的初始化

timekeeper的初始化由timekeeping_init完成，该函数在start_kernel的初始化序列中被调用，timekeeping_init首先从RTC中获取当前时间：

```
	void __init timekeeping_init(void)  
	{  
		struct clocksource *clock;  
		unsigned long flags;  
		struct timespec now, boot;  
	  
		read_persistent_clock(&now);  
		read_boot_clock(&boot);  

	然后对锁和ntp进行必要的初始化：

		seqlock_init(&timekeeper.lock);  
	  
		ntp_init();  

	接着获取默认的clocksource，如果平台没有重新实现clocksource_default_clock函数，
	默认的clocksource就是基于jiffies的clocksource_jiffies，
	然后通过timekeeper_setup_inernals内部函数把timekeeper和clocksource进行关联：

		write_seqlock_irqsave(&timekeeper.lock, flags);  
		clock = clocksource_default_clock();  
		if (clock->enable)  
			clock->enable(clock);  
		timekeeper_setup_internals(clock);  

	利用RTC的当前时间，初始化xtime，raw_time，wall_to_monotonic等字段：

		timekeeper.xtime.tv_sec = now.tv_sec;  
		timekeeper.xtime.tv_nsec = now.tv_nsec;  
		timekeeper.raw_time.tv_sec = 0;  
		timekeeper.raw_time.tv_nsec = 0;  
		if (boot.tv_sec == 0 && boot.tv_nsec == 0) {  
			boot.tv_sec = timekeeper.xtime.tv_sec;  
			boot.tv_nsec = timekeeper.xtime.tv_nsec;  
		}  
		set_normalized_timespec(&timekeeper.wall_to_monotonic,  
				-boot.tv_sec, -boot.tv_nsec);  

	最后，初始化代表实时时间和monotonic时间之间偏移量的offs_real字段，total_sleep_time字段初始化为0：


		update_rt_offset();  
		timekeeper.total_sleep_time.tv_sec = 0;  
		timekeeper.total_sleep_time.tv_nsec = 0;  
		write_sequnlock_irqrestore(&timekeeper.lock, flags);  

	}
```

xtime字段因为是保存在内存中，系统掉电后无法保存时间信息，所以每次启动时都要通过timekeeping_init从RTC中同步正确的时间信息。其中，read_persistent_clock和read_boot_clock是平台级的函数，分别用于获取RTC硬件时间和启动时的时间，不过值得注意到是，到目前为止（我的代码树基于3.4版本），ARM体系中，只有tegra和omap平台实现了read_persistent_clock函数。如果平台没有实现该函数，内核提供了一个默认的实现：

```
	void __attribute__((weak)) read_persistent_clock(struct timespec *ts)  
	{  
		ts->tv_sec = 0;  
		ts->tv_nsec = 0;  
	}  
```

```
	void __attribute__((weak)) read_boot_clock(struct timespec *ts)  
	{  
		ts->tv_sec = 0;  
		ts->tv_nsec = 0;  
	}  
```

那么，其他ARM平台是如何初始化xtime的？答案就是CONFIG_RTC_HCTOSYS这个内核配置项，打开该配置后，driver/rtc/hctosys.c将会编译到系统中，由rtc_hctosys函数通过do_settimeofday在系统初始化时完成xtime变量的初始化：

```
	static int __init rtc_hctosys(void)   
	{   
			......   
			err = rtc_read_time(rtc, &tm);   
			......  
			rtc_tm_to_time(&tm, &tv.tv_sec);   
			do_settimeofday(&tv);   
			......   
			return err;   
	}   
	late_initcall(rtc_hctosys);  
```

#### 4. 时间的更新

xtime一旦初始化完成后，timekeeper就开始独立于RTC，利用自身关联的clocksource进行时间的更新操作，根据内核的配置项的不同，更新时间的操作发生的频度也不尽相同，如果没有配置NO_HZ选项，通常每个tick的定时中断周期，do_timer会被调用一次，相反，如果配置了NO_HZ选项，可能会在好几个tick后，do_timer才会被调用一次，当然传入的参数是本次更新离上一次更新时相隔了多少个tick周期，系统会保证在clocksource的max_idle_ns时间内调用do_timer，以防止clocksource的溢出：

```
	void do_timer(unsigned long ticks)  
	{  
		jiffies_64 += ticks;  
		update_wall_time();  
		calc_global_load(ticks);  
	}  
```

在do_timer中，jiffies_64变量被相应地累加，然后在update_wall_time中完成xtime等时间的更新操作，更新时间的核心操作就是读取关联clocksource的计数值，累加到xtime等字段中，其中还设计ntp时间的调整等代码，详细的代码就不贴了。

#### 5. 获取时间

timekeeper提供了一系列的接口用于获取各种时间信息。
```
	void getboottime(struct timespec *ts);    获取系统启动时刻的实时时间
	void get_monotonic_boottime(struct timespec *ts);     获取系统启动以来所经过的时间，包含休眠时间
	ktime_t ktime_get_boottime(void);   获取系统启动以来所经过的c时间，包含休眠时间，返回ktime类型
	ktime_t ktime_get(void);    获取系统启动以来所经过的c时间，不包含休眠时间，返回ktime类型
	void ktime_get_ts(struct timespec *ts) ;   获取系统启动以来所经过的c时间，不包含休眠时间，返回timespec结构
	unsigned long get_seconds(void);    返回xtime中的秒计数值
	struct timespec current_kernel_time(void);    返回内核最后一次更新的xtime时间，不累计最后一次更新至今clocksource的计数值
	void getnstimeofday(struct timespec *ts);    获取当前时间，返回timespec结构
	void do_gettimeofday(struct timeval *tv);    获取当前时间，返回timeval结构
```
