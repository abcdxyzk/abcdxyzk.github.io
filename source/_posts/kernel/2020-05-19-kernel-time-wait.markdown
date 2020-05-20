---
layout: post
title: "TIME-WAIT"
date: 2020-05-19 16:00:00 +0800
comments: false
categories:
- 2020
- 2020~05
- kernel
- kernel~net
tags:
---

#### 1. tw_reuse，tw_recycle 必须在客户端和服务端 timestamps 开启时才管用
```
	cat /proc/sys/net/ipv4/tcp_timestamps
```

#### 2. tw_reuse 只对客户端起作用

开启后超过1s的time-wait sk被reuse, 如下代码。否则inet_hash_connect会继续尝试寻在可用端口。

tcp_v4_connect() -> inet_hash_connect() -> `__inet_check_established()` -> twsk_unique() -> tcp_twsk_unique()

vim net/ipv4/tcp_ipv4.c
```
int tcp_twsk_unique(struct sock *sk, struct sock *sktw, void *twp)
{
        const struct tcp_timewait_sock *tcptw = tcp_twsk(sktw);
        struct tcp_sock *tp = tcp_sk(sk);

        if (tcptw->tw_ts_recent_stamp &&
            (twp == NULL || (sysctl_tcp_tw_reuse &&
                             get_seconds() - tcptw->tw_ts_recent_stamp > 1))) {
                tp->write_seq = tcptw->tw_snd_nxt + 65535 + 2;
                if (tp->write_seq == 0)
                        tp->write_seq = 1;
                tp->rx_opt.ts_recent       = tcptw->tw_ts_recent;
                tp->rx_opt.ts_recent_stamp = tcptw->tw_ts_recent_stamp;
                sock_hold(sktw);
                return 1;
        }

        return 0;
}
```

#### 3. tw_recycle 和 TCP_TIMEWAIT_LEN

tw_recycle 对客户端和服务器同时起作用，有两个作用：  
a) 开启后在 `3*RTO` 后回收 sk。没开启在 TCP_TIMEWAIT_LEN = 60 后回收 sk。  
b) tcp会缓存每个连接最新的时间戳，后续请求中如果时间戳小于缓存的时间戳，相应的数据包会被丢弃。如果多个客户端在NAT后面就会出问题。

有些内核删除了b功能，如tlinux。 https://github.com/torvalds/linux/commit/4396e46187ca5070219b81773c4e65088dac50cc

最新的内核删除了a、b两个功能，且 TCP_TIMEWAIT_LEN 不可配置。。。

#### 4. tcp_max_tw_buckets
```
	cat /proc/sys/net/ipv4/tcp_max_tw_buckets
```

time-wait sk 的最大数量。

设置成0就部不分配time-wait sk，只回一个ack，如果ack丢了下次就只能回rst了，测试的时候可以用。

#### 5. 服务端处于 time-wait 时收包处理

[TIME_WAIT状态下对接收到的数据包如何处理](/blog/2015/09/29/kernel-net-timewait_state/)

