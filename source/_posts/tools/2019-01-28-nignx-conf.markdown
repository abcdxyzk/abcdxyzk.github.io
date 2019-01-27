---
layout: post
title: "nginx ipv6、TCP_DEFER_ACCEPT"
date: 2019-01-28 04:06:00 +0800
comments: false
categories:
- 2019
- 2019~01
- tools
- tools~nginx
tags:
---

#### curl 7.61.1

[curl 7.61.1](/download/tools/curl_7.61.1)

#### TCP_DEFER_ACCEPT
```
	server {
		listen  80 deferred;
		...

```
 deferred
  instructs to use a deferred accept() (the TCP_DEFER_ACCEPT socket option) on Linux. 


#### ipv6
```
	server {
		listen  [::]:8080; # ipv6only=on;
		...
```
 ipv6only=on|off
  this parameter (0.7.42) determines (via the IPV6_V6ONLY socket option) whether an IPv6 socket listening on a wildcard address [::] will accept only IPv6 connections or both IPv6 and IPv4 connections. This parameter is turned on by default. It can only be set once on start.

  Prior to version 1.3.4, if this parameter was omitted then the operating system’s settings were in effect for the socket. 


