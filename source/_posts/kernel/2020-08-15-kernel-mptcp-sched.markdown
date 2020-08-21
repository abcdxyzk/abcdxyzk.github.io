---
layout: post
title: "MPTCP sched"
date: 2020-08-15 01:02:00 +0800
comments: false
categories:
- 2020
- 2020~08
- kernel
- kernel~mptcp
tags:
- mptcp
---

```
	$ git grep mptcp_register_scheduler net/mptcp/
	net/mptcp/mptcp_blest.c:        if (mptcp_register_scheduler(&mptcp_sched_blest))
	net/mptcp/mptcp_redundant.c:    if (mptcp_register_scheduler(&mptcp_sched_red))
	net/mptcp/mptcp_rr.c:   if (mptcp_register_scheduler(&mptcp_sched_rr))
	net/mptcp/mptcp_sched.c:int mptcp_register_scheduler(struct mptcp_sched_ops *sched)
```

#### blest


#### redundant


#### rr
