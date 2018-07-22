---
layout: post
title: "反向路径过滤 -- reverse path filt"
date: 2018-07-23 01:11:00 +0800
comments: false
categories:
- 2018
- 2018~07
- kernel
- kernel~net
tags:
---

find /proc/ -name rp_filter -exec cat {} \;

find /proc/ -name rp_filter -exec sh -c "echo 0 > {}" \;

--------

http://blog.chinaunix.net/uid-20417916-id-3050031.html

反向路径过滤 -- reverse path filter

#### 一、原理
先介绍个非对称路由的概念

参考《Understanding Linux Network Internals》三十章，

##### 30.2. Essential Elements of Routing

Symmetric routes and asymmetric routes

Usually, the route taken from Host A to Host B is the same as the route used to get back from Host B to Host A; the route is then called symmetric . In complex setups, the route back may be different; in this case, it is asymmetric.

关于反向路径过滤，参考《Understanding Linux Network Internals》三十一章，

##### 31.7. Reverse Path Filtering

We saw what an asymmetric route is in the section "Essential Elements of Routing in Chapter 30. Asymmetric routes are not common, but may be necessary in certain cases. The default behavior of Linux is to consider asymmetric routing suspicious and therefore to drop any packet whose source IP address is not reachable through the device the packet was received from, according to the routing table.

However, this behavior can be tuned via /proc on a per-device basis, as we will see in Chapter 36. See also the section "Input Routing" in Chapter 35.

#### 二、检查流程
如果一台主机（或路由器）从接口A收到一个包，其源地址和目的地址分别是10.3.0.2和10.2.0.2，
即, 如果启用反向路径过滤功能，它就会以为关键字去查找路由表，如果得到的输出接口不为A，则认为反向路径过滤检查失败，它就会丢弃该包。

关于反向路径过滤，ipv4中有个参数，这个参数的说明在Documentation/networking/ip-sysctl.txt中。
```
	rp_filter - INTEGER
	    0 - No source validation.
	    1 - Strict mode as defined in RFC3704 Strict Reverse Path
	        Each incoming packet is tested against the FIB and if the interface
	        is not the best reverse path the packet check will fail.
	        By default failed packets are discarded.
	    2 - Loose mode as defined in RFC3704 Loose Reverse Path
	        Each incoming packet's source address is also tested against the FIB
	        and if the source address is not reachable via any interface
	        the packet check will fail.
	
	    Current recommended practice in RFC3704 is to enable strict mode
	    to prevent IP spoofing from DDos attacks. If using asymmetric routing
	    or other complicated routing, then loose mode is recommended.
	
	    The max value from conf/{all,interface}/rp_filter is used
	    when doing source validation on the {interface}.
	
	    Default value is 0. Note that some distributions enable it
	    in startup scripts.
```

#### 三、源代码分析

git commit 373da0a2a33018d560afcb2c77f8842985d79594

```
	net/ipv4/fib_frontend.c
	 192 int fib_validate_source(struct sk_buff *skb, __be32 src, __be32 dst, u8 tos,
	 193                         int oif, struct net_device *dev, __be32 *spec_dst,
	 194                         u32 *itag)
	 195 {
	             // 是否启用反向路径过滤
	 216         /* Ignore rp_filter for packets protected by IPsec. */
	 217         rpf = secpath_exists(skb) ? 0 : IN_DEV_RPFILTER(in_dev);
	 
	             // 检查路由表
	             // 注意这里的源地址贺目的地址是反过来的，
	             // 看看其他函数是如何调用fib_validate_source()就明白了。
	 227         if (fib_lookup(net, &fl4, &res))
	 228                 goto last_resort;
	
	             // 运行到这里，说明反向路由是可达的
	             // 下面分成两种情况检查输出设备是否就是输入设备
	 237 #ifdef CONFIG_IP_ROUTE_MULTIPATH
	             // 启用多路径时，任意一个匹配，就用它了
	 238         for (ret = 0; ret < res.fi->fib_nhs; ret++) {
	 239                 struct fib_nh *nh = &res.fi->fib_nh[ret];
	 240
	 241                 if (nh->nh_dev == dev) {
	 242                         dev_match = true;
	 243                         break;
	 244                 }
	 245         }
	 246 #else
	 247         if (FIB_RES_DEV(res) == dev)
	 248                 dev_match = true;
	 249 #endif
	 250         if (dev_match) {
	             // 反向路径过滤检查成功了，返回
	 251                 ret = FIB_RES_NH(res).nh_scope >= RT_SCOPE_HOST;
	 252                 return ret;
	 253         }
	 254         if (no_addr)
	 255                 goto last_resort;
	             // 运行到这里，说明反向路径检查是失败的，
	             // 如果rpf为1，表示反向路径检查必须成功才能正常返回，
	             // 否则只好返回错误。
	 256         if (rpf == 1)
	 257                 goto e_rpf;
	 278 e_rpf:
	 279         return -EXDEV;
```

#### 五、如何解决
两种方法：

##### 1 On R2:
ip route add 10.3.0.0/16 via 10.2.0.2

增加一条关于10.3.0.0/16子网的路由。

##### 2 On R2:
/etc/sysctl.conf

net.ipv4.conf.default.rp_filter = 0

禁用反向路径检查。
