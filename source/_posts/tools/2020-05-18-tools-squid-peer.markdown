---
layout: post
title: "squid 代理转发"
date: 2020-05-18 14:55:00 +0800
comments: false
categories:
- 2020
- 2020~05
- tools
- tools~squid
tags:
---

https://www.cmdschool.org/archives/4673

#### Squid的层次结构

![](/images/tools/20200518-1.png)

图中绿色线代表父子关系的层次结构（上游下游关系）

图中蓝色代表兄弟关系的层次结构（平等关系）

#### 代理转发

Squid使用“cache_peer”指令提供父节点的缓存

##### cache_peer指令的模式

never direct模式，父节点失败不能直接连接源服务器，如果父节点失败或无法访问，则每个请求都导致错误消息

prefer direct模式，父节点失败允许直接连接源服务器，如果父节点失败或无法访问，则连接到源服务器而不是父节点

注：失败是指没有ICP或HTCP回复

##### never direct模式
```
	cache_peer parentcache.foo.com parent 3128 0 no-query default
	never_direct allow all
```

以上使用never_direct指令宣告父节点失败不能直接连接源服务器

##### prefer direct模式
```
	cache_peer parentcache.foo.com parent 3128 0 no-query
	prefer_direct off
	nonhierarchical_direct off
```
以上使用prefer_direct指令宣告首选从DNS中列出源服务器尝试

以上使用nonhierarchical_direct指令宣告往父节点的请求继续发送

hierarchy_stoplist指令是prefer direct模式的另外一种实现（适用于Squid-3.2之前的版本）

##### cache_peer指令的使用
```
	cache_peer hostname type http-port icp-port [options]
```
hostname参数，指定转发的代理服务器主机名称（IP地址亦可）

type参数，可选值有“parent”（父母）、“sibling”（兄弟）和“multicast”（多播）

http-port参数，指定转发的代理服务器通讯端口，默认值3128

icp-port参数，查询对象的邻居缓存，如果不支持ICP或HTCP，设置为0

options参数，可选的其他选项（不一一列举）

http://www.squid-cache.org/Doc/config/cache_peer/
