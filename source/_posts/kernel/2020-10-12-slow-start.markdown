---
layout: post
title: "hybrid slow start 混合慢启动算法"
date: 2020-10-12 13:37:00 +0800
comments: false
categories:
- 2020
- 2020~10
- kernel
- kernel~tcp
tags:
---

https://www.jianshu.com/p/f2edbaca4f2c

传统的单纯采用指数增长的慢启动算法有一个无法避免的问题，在临界进入拥塞避免阶段时，特别是在高带宽长距离网络中，容易出现大规模丢包，进而导致大量数据包重传，也有可能出现timeout，致使网络带宽利用率下降。

Hybrid Slow Start，它在传统的慢启动算法中加入了判断机制，强制从慢启动转入拥塞避免。这里主要说说其在CUBIC中是怎么实现的。

### 变量介绍

```
	#define HYSTART_ACK_TRAIN      0x1 //进入拥塞避免的条件
	#define HYSTART_DELAY          0x2 //进入拥塞避免的条件
	#define HYSTART_MIN_SAMPLES    8   //表示至少取一个RTT的前8个ACK作为样本
	#define HYSTART_DELAY_MIN      (4u<<3) 
	#define HYSTART_DELAY_MAX      (16u<<3)
	/* if x > HYSTART_DELAY_MAX，return HYSTART_DELAY_MAX 
	 * else if x < HYSTART_DELAY_MIN，return HYATART_DELAY_MIN
	 * else return x
	 */
	#define HYSTART_DELAY_THRESH clamp(x, HYSTART_DELAY_MIN, HYSTART_DELAY_MAX)
	static int hystart __read_mostly = 1;
	static int hystart_detect __read_mostly = HYSTART_ACK_TRAIN | HYSART_DELAY;
	static int hystart_low_window __read_mostly = 16;
	static int hystart_ack_delta __read_mostly = 2;

	struct bictcp {
		...
		u32    delay_min;   //全局最小rtt
		u32    round_start; //记录慢启动的起始时间
		u32    curr_rtt;    //记录样本中的最小rtt
		u8      found;
		u8      sample_cnt; //样本计数变量
		...
	};
```

### 两类退出slow start机制

在Hybrid Slow Start算法中给出了种类判断机制用来退出慢启动进入拥塞避免，分别是ACKs train length和Increase in packet delays。

#### ACKS train length

这里给出一段原文描述，在这段描述中说了怎么测ACKs train length以及为什么要用ACKs train length。

The ACK train length is measured by calculating the sum of inter-arrival times of all the closely spaced ACKs within an RTT round. The train length is strongly affected by the bottleneck bandwidth, routing delays and buffer sizes along the path, and is easily stretched out by congestion caused by cross traffic in the path, so by estimating the train length we can reliably find a safe exit point of Slow Start.

#### Increase in packet delays

同样还是一段原文描述，如果你问我为什么不直接翻译成中文，我不会回答你这个问题的。

Increase in packet delays during Slow Start may indicate the possibility of the bottleneck router being congested.

但是Increase in packet delays的测量会受到bursty transmission的影响，所以只测一个RTT中刚开始的几个数据包的往返时间来避免bursty transission的影响，在后面给出的code中会看到

### 函数实现

hystart重置函数

```
	static inline void bictcp_hystart_reset(struct sock *sk)
	{
		struct tcp_sock *tp = tcp_sk(sk);
		struct bictcp *ca = inet_csk_ca(sk);

		ca->round_start = ca->last_ack = bictcp_clock(); //记录慢启动的开始时间
		ca->end_seq = tp->snd_nxt;
		ca->curr_rtt = 0;   //重置样本最小rtt为0
		ca->sample_cnt = 0; //重置样本计数为0
	}
```

Hybrid Slow Start实现的核心部分

```
	static void hystart_update(struct sock *sk, u32 delay)
	{
		struct tcp_sock *tp = tcp_sk(sk);
		struct bictcp *ca = inet_csk_ca(sk);

		//如果ca->found & hystart_detect为真，表示应该进入拥塞避免
		if (!(ca->found & hystart_detect)) {
			u32 now = bictcp_clock(); //获取当前时间

			/* first detection parameter - ack-train detection */
			/* 前后到来的两个ACK的间隔时间小于hystart_ack_delta才有效 */
			if ((s32)(now - ca->last_ack) <= hystart_ack_delta) {
				ca->last_ack = now;  //更新上一个ACK到来的时间
				/* 每次慢启动时会重置round_start为0，结合前面的if条件，下面的
				 * if成立的条件是：从慢启动开始到现在经过的时间如果大于
				 * delay_min>>4，那么可以进入拥塞避免了。至于为什么选
				 * delay_min>>4这个值，鬼知道。
				 */
				if ((s32)(now - ca->round_start) > ca->delay_min >> 4)
					ca->found |= HYSTART_ACK_TRAIN;
			}   

			/* obtain the minimum delay of more than sampling packets */
			/* 如果样本计数小于HYSTART_MIN_SAMPLES(默认为8) */
			if (ca->sample_cnt < HYSTART_MIN_SAMPLES) {
				if (ca->curr_rtt == 0 || ca->curr_rtt > delay)
					ca->curr_rtt = delay;/* 更新样本中的最小rtt */

				ca->sample_cnt++;
			} else {//如果样本大于8了，那么就可以判断是否要进入拥塞避免了
				/* 如果前面8个样本中的最小rtt大于全局最小rtt与阈值的和，那么表示网络出
				 * 现了拥塞，应立马进入拥塞避免阶段，HYSTART_DELAY_THRESH()的返
				 * 回值在前面的变量介绍中有说明。
				if (ca->curr_rtt > ca->delay_min +
					HYSTART_DELAY_THRESH(ca->delay_min>>4))
					ca->found |= HYSTART_DELAY;
			}   
			/*  
			 * Either one of two conditions are met,
			 * we exit from slow start immediately.
			 */
			/* 如果为真就进入拥塞避免 */
			if (ca->found & hystart_detect)
				tp->snd_ssthresh = tp->snd_cwnd;
		}   
	}
```

