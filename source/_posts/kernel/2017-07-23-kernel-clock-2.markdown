---
layout: post
title: "Linux时间子系统之二：表示时间的单位和结构"
date: 2017-07-23 13:11:00 +0800
comments: false
categories:
- 2017
- 2017~07
- kernel
- kernel~clock
tags:
---

http://blog.csdn.net/droidphone/article/details/7979295

#### 1. jiffies

内核用jiffies变量记录系统启动以来经过的时钟滴答数，它的声明如下：

```
	extern u64 __jiffy_data jiffies_64;
	extern unsigned long volatile __jiffy_data jiffies;
```

可见，在32位的系统上，jiffies是一个32位的无符号数，系统每过1/HZ秒，jiffies的值就会加1，最终该变量可能会溢出，所以内核同时又定义了一个64位的变量jiffies_64，链接的脚本保证jiffies变量和jiffies_64变量的内存地址是相同的，通常，我们可以直接访问jiffies变量，但是要获得jiffies_64变量，必须通过辅助函数get_jiffies_64来实现。jiffies是内核的低精度定时器的计时单位，所以内核配置的HZ数决定了低精度定时器的精度，如果HZ数被设定为1000，那么，低精度定时器（timer_list）的精度就是1ms=1/1000秒。因为jiffies变量可能存在溢出的问题，所以在用基于jiffies进行比较时，应该使用以下辅助宏来实现：

```
	time_after(a,b)
	time_before(a,b)
	time_after_eq(a,b)
	time_before_eq(a,b)
	time_in_range(a,b,c)
```

同时，内核还提供了一些辅助函数用于jiffies和毫秒以及纳秒之间的转换：

```
	unsigned int jiffies_to_msecs(const unsigned long j);
	unsigned int jiffies_to_usecs(const unsigned long j);
	unsigned long msecs_to_jiffies(const unsigned int m);
	unsigned long usecs_to_jiffies(const unsigned int u);
```

#### 2. struct timeval
timeval由秒和微秒组成，它的定义如下：

```
	struct timeval {
		__kernel_time_t	 tv_sec;         /* seconds */
		__kernel_suseconds_t	tv_usec; /* microseconds */
	};
```

`__kernel_time_t` 和`__kernel_suseconds_t` 实际上都是long型的整数。gettimeofday和settimeofday使用timeval作为时间单位。

### 3. struct timespec
timespec由秒和纳秒组成，它的定义如下：

```
	struct timespec {
		__kernel_time_t tv_sec;          /* seconds */
		long		tv_nsec;         /* nanoseconds */
	};
```

同样地，内核也提供了一些辅助函数用于jiffies、timeval、timespec之间的转换：

```
	static inline int timespec_equal(const struct timespec *a, const struct timespec *b);
	static inline int timespec_compare(const struct timespec *lhs, const struct timespec *rhs);
	static inline int timeval_compare(const struct timeval *lhs, const struct timeval *rhs);
	extern unsigned long mktime(const unsigned int year, const unsigned int mon,
					const unsigned int day, const unsigned int hour,
					const unsigned int min, const unsigned int sec);
	extern void set_normalized_timespec(struct timespec *ts, time_t sec, s64 nsec);
	static inline struct timespec timespec_add(struct timespec lhs, struct timespec rhs);
	static inline struct timespec timespec_sub(struct timespec lhs, struct timespec rhs);
	
	static inline s64 timespec_to_ns(const struct timespec *ts);
	static inline s64 timeval_to_ns(const struct timeval *tv);
	extern struct timespec ns_to_timespec(const s64 nsec);
	extern struct timeval ns_to_timeval(const s64 nsec);
	static __always_inline void timespec_add_ns(struct timespec *a, u64 ns);
```

```
	unsigned long timespec_to_jiffies(const struct timespec *value);
	void jiffies_to_timespec(const unsigned long jiffies, struct timespec *value);
	unsigned long timeval_to_jiffies(const struct timeval *value);
	void jiffies_to_timeval(const unsigned long jiffies, struct timeval *value);
```

timekeeper中的xtime字段用timespec作为时间单位。

#### 4. struct ktime

linux的通用时间架构用ktime来表示时间，为了兼容32位和64位以及big-little endian系统，ktime结构被定义如下：

```
	union ktime {
		s64 tv64;
	#if BITS_PER_LONG != 64 && !defined(CONFIG_KTIME_SCALAR)
		struct {
	# ifdef __BIG_ENDIAN
		s32 sec, nsec;
	# else
		s32 nsec, sec;
	# endif
		} tv;
	#endif
	};
```

64位的系统可以直接访问tv64字段，单位是纳秒，32位的系统则被拆分为两个字段：sec和nsec，并且照顾了大小端的不同。高精度定时器通常用ktime作为计时单位。下面是一些辅助函数用于计算和转换：

```
	ktime_t ktime_set(const long secs, const unsigned long nsecs); 
	ktime_t ktime_sub(const ktime_t lhs, const ktime_t rhs); 
	ktime_t ktime_add(const ktime_t add1, const ktime_t add2); 
	ktime_t ktime_add_ns(const ktime_t kt, u64 nsec); 
	ktime_t ktime_sub_ns(const ktime_t kt, u64 nsec); 
	ktime_t timespec_to_ktime(const struct timespec ts); 
	ktime_t timeval_to_ktime(const struct timeval tv); 
	struct timespec ktime_to_timespec(const ktime_t kt); 
	struct timeval ktime_to_timeval(const ktime_t kt); 
	s64 ktime_to_ns(const ktime_t kt); 
	int ktime_equal(const ktime_t cmp1, const ktime_t cmp2); 
	s64 ktime_to_us(const ktime_t kt); 
	s64 ktime_to_ms(const ktime_t kt);
	ktime_t ns_to_ktime(u64 ns);
```

