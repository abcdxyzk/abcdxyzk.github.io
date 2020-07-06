---
layout: post
title: "MPTCP_OPTION"
date: 2020-07-07 02:51:00 +0800
comments: false
categories:
- 2020
- 2020~07
- kernel
- kernel~mptcp
tags:
---

解析见 mptcp_parse_options()

### MPTCP_SUB_CAPABLE
```
	#define MPTCP_SUB_CAPABLE                       0
	#define MPTCP_SUB_LEN_CAPABLE_SYN               12
	#define MPTCP_SUB_LEN_CAPABLE_SYN_ALIGN         12
	#define MPTCP_SUB_LEN_CAPABLE_ACK               20
	#define MPTCP_SUB_LEN_CAPABLE_ACK_ALIGN         20
```
最初的三次握手时用

### MPTCP_SUB_JOIN
```
	#define MPTCP_SUB_JOIN                  1
	#define MPTCP_SUB_LEN_JOIN_SYN          12
	#define MPTCP_SUB_LEN_JOIN_SYN_ALIGN    12
	#define MPTCP_SUB_LEN_JOIN_SYNACK       16
	#define MPTCP_SUB_LEN_JOIN_SYNACK_ALIGN 16
	#define MPTCP_SUB_LEN_JOIN_ACK          24
	#define MPTCP_SUB_LEN_JOIN_ACK_ALIGN    24
```
第二次、第三次、。。。握手时用

### MPTCP_SUB_DSS
```
	#define MPTCP_SUB_DSS           2
```

### MPTCP_SUB_ADD_ADDR, MPTCP_SUB_REMOVE_ADDR
```
	#define MPTCP_SUB_ADD_ADDR              3
	#define MPTCP_SUB_LEN_ADD_ADDR4         8
	#define MPTCP_SUB_LEN_ADD_ADDR4_VER1    16
	#define MPTCP_SUB_LEN_ADD_ADDR6         20
	#define MPTCP_SUB_LEN_ADD_ADDR6_VER1    28
	#define MPTCP_SUB_LEN_ADD_ADDR4_ALIGN   8
	#define MPTCP_SUB_LEN_ADD_ADDR4_ALIGN_VER1      16
	#define MPTCP_SUB_LEN_ADD_ADDR6_ALIGN   20
	#define MPTCP_SUB_LEN_ADD_ADDR6_ALIGN_VER1      28

	#define MPTCP_SUB_REMOVE_ADDR   4
	#define MPTCP_SUB_LEN_REMOVE_ADDR       4
```

fullmesh 模式通告ip

### MPTCP_SUB_PRIO
```
	#define MPTCP_SUB_PRIO          5
	#define MPTCP_SUB_LEN_PRIO      3
	#define MPTCP_SUB_LEN_PRIO_ADDR 4
	#define MPTCP_SUB_LEN_PRIO_ALIGN        4
```
优先级？

### MPTCP_SUB_FAIL
```
	#define MPTCP_SUB_FAIL          6
	#define MPTCP_SUB_LEN_FAIL      12 
	#define MPTCP_SUB_LEN_FAIL_ALIGN        12
```

### MPTCP_SUB_FCLOSE
```
	#define MPTCP_SUB_FCLOSE        7
	#define MPTCP_SUB_LEN_FCLOSE    12
	#define MPTCP_SUB_LEN_FCLOSE_ALIGN      12
```

