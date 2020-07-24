---
layout: post
title: "MPTCP_VERSION"
date: 2020-07-07 02:38:00 +0800
comments: false
categories:
- 2020
- 2020~07
- kernel
- kernel~mptcp
tags:
- mptcp
---

#### mptcp_version

只有两个版本 v0、v1

v1: 在option=MPTCP_SUB_ADD_ADDR 时需要加密，收包时在 mptcp_handle_add_addr 验证。

v0: 没有加密
