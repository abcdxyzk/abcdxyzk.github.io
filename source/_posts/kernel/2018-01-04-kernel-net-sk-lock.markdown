---
layout: post
title: "sk 的锁，spin_lock_bh、lock_sock"
date: 2018-01-04 02:20:00 +0800
comments: false
categories:
- 2018
- 2018~01
- kernel
- kernel~net
tags:
---

#### 一、修改sk的锁 sk_lock.slock

tcp协议栈对struct sock *sk有两把锁，第一把是sk_lock.slock，第二把则是sk_lock.owned。sk_lock.slock用于获取struct sock *sk对象的成员的修改权限；sk_lock.owned用于区分当前是进程上下文或是软中断上下文，为进程上下文时sk_lock.owned会被置1，中断上下文为0。

如果是要对sk修改，首先是必须拿锁sk_lock.slock，其后是判断当前是软中断或是进程上下文，如果是进程上下文，那么一般也不能修改sk


中断上下文可以用下面的锁，也就是下面的锁只有 spin_lock
```
	/* BH context may only use the following locking interface. */
	#define bh_lock_sock(__sk)      spin_lock(&((__sk)->sk_lock.slock))
	#define bh_lock_sock_nested(__sk) \
					spin_lock_nested(&((__sk)->sk_lock.slock), \
					SINGLE_DEPTH_NESTING)
	#define bh_unlock_sock(__sk)    spin_unlock(&((__sk)->sk_lock.slock))
```

非中断上下文可以直接用 spin_lock_bh(&((sk)->sk_lock.slock))

获得sk_lock.slock 锁后还要判断 sock_owned_by_user(sk), 如果被进程上下文占用也一般不能操作sk
```
	#define sock_owned_by_user(sk)  ((sk)->sk_lock.owned)
```

#### 二、进程上下文获取sk

```
	static inline void lock_sock(struct sock *sk)
	{
		lock_sock_nested(sk, 0);
	}

	void lock_sock_nested(struct sock *sk, int subclass)
	{
		might_sleep();

		// 先获取 sk_lock.slock 锁
		spin_lock_bh(&sk->sk_lock.slock);
		// 如果有进程占用sk，则调__lock_sock等待占用sk的进程结束
		if (sk->sk_lock.owned)
			__lock_sock(sk);

		// 此时占用sk的进程结束了，但前进程就可以占用sk
		sk->sk_lock.owned = 1;
		// 进程占用sk时不占 sk_lock.slock
		spin_unlock(&sk->sk_lock.slock);

		/*
		 * The sk_lock has mutex_lock() semantics here:
		 */
		mutex_acquire(&sk->sk_lock.dep_map, subclass, 0, _RET_IP_);
		local_bh_enable();
	}

	static void __lock_sock(struct sock *sk)
		__releases(&sk->sk_lock.slock)
		__acquires(&sk->sk_lock.slock)
	{
		DEFINE_WAIT(wait);

		for (;;) {
			prepare_to_wait_exclusive(&sk->sk_lock.wq, &wait,
						TASK_UNINTERRUPTIBLE);
			spin_unlock_bh(&sk->sk_lock.slock);
			schedule();
			spin_lock_bh(&sk->sk_lock.slock);
			if (!sock_owned_by_user(sk))
				break;
		}
		finish_wait(&sk->sk_lock.wq, &wait);
	}
```

```
	void release_sock(struct sock *sk)
	{
		/*
		 * The sk_lock has mutex_unlock() semantics:
		 */
		mutex_release(&sk->sk_lock.dep_map, 1, _RET_IP_);

		spin_lock_bh(&sk->sk_lock.slock);
		if (sk->sk_backlog.tail)
			__release_sock(sk); // 处理backlog的包

		/* Warning : release_cb() might need to release sk ownership,
		 * ie call sock_release_ownership(sk) before us.
		 */
		if (sk->sk_prot->release_cb)
			sk->sk_prot->release_cb(sk);

		// 获取sk_lock.slock，然后清除 sk_lock.owned
		sock_release_ownership(sk);
		if (waitqueue_active(&sk->sk_lock.wq))
			wake_up(&sk->sk_lock.wq);
		spin_unlock_bh(&sk->sk_lock.slock);
	}

	static inline void sock_release_ownership(struct sock *sk)
	{
		sk->sk_lock.owned = 0;
	}
```

