---
layout: post
title: "MPTCP cong"
date: 2020-08-15 01:00:00 +0800
comments: false
categories:
- 2020
- 2020~08
- kernel
- kernel~mptcp
tags:
- mptcp
---

不设置时，默认的就是cubic

```
	$ git grep tcp_congestion_ops net/mptcp/
	net/mptcp/mctcp_desync.c:static struct tcp_congestion_ops mctcp_desync = {
	net/mptcp/mptcp_balia.c:static struct tcp_congestion_ops mptcp_balia = {
	net/mptcp/mptcp_coupled.c:static struct tcp_congestion_ops mptcp_ccc = {
	net/mptcp/mptcp_olia.c:static struct tcp_congestion_ops mptcp_olia = {
	net/mptcp/mptcp_wvegas.c:static struct tcp_congestion_ops mptcp_wvegas __read_mostly = {
```

#### desync


#### balia


#### ccc


#### olia


#### wvegas
