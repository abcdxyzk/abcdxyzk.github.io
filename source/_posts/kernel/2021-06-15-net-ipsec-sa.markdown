---
layout: post
title: "IKE SA和IPSec SA的区别"
date: 2021-06-15 22:15:00 +0800
comments: false
categories:
- 2021
- 2021~06
- kernel
- kernel~ipsec
tags:
---

https://blog.csdn.net/jiangwlee/article/details/7395903

刚接触IPSec的时候，一直很奇怪，为什么要做两阶段的协商？先协商出来一个IKE SA，然后再IKE SA的基础上协商出来一个IPSec SA。直接一步到位协商出IPSec SA不是很好吗？但是在实际应用中，直接协商IPSec SA就显得不是那么有效率了。打个比方，某公司A有个子公司B，为了方便子公司B的员工访问总公司A的内部数据，在双方的安全网关上部署VPN，使用IPSec进行数据加密。如果双方都使用IKEv1，而且只有一个协商阶段，直接协商出IPSec SA，那么每一次协商可能都需要6个Main Mode消息和3个Quick Mode消息。这样会产生大量的协商消息，降低了网络的利用率。而如果采用两阶段协商，只需要在网关间协商出一个IKE SA，然后用这个SA来为应用数据流协商IPSec SA，那么每个IPSec SA只需要一个Quick Mode即可。所以，两阶段的好处在于，可以通过第一阶段协商出IKE SA用作IPSec SA协商的载体，从而减少IPSec SA协商的开销。


那么IKE SA和IPSec SA的区别在哪儿呢？从定义上来看，IKE SA负责IPSec SA的建立和维护，起控制作用；IPSec SA负责具体的数据流加密。比如一个HTTP请求，可能最终需要用到IPSec SA定义的ESP协议和相关ESP加密算法。


IKE SA和IPSec SA协商的内容也是不一样的，如下：

#### 1. IKEv1的IKE SA协商内容

参考：http://www.iana.org/assignments/ipsec-registry

a. 加密算法

b. 哈希算法

c. 认证方法 - 如证书认证、Pre-shared Key

d. PRF算法 - 用来产生加解密密钥

e. DH算法和参数

f. Key长度 - 某些算法，如AES-CBC的key长度是可变的，可以通过Attribute来协商Key长度

g. SA的生存时间


#### 2. IKEv2的IKE SA协商内容

参考：http://www.iana.org/assignments/ikev2-parameters/ikev2-parameters.xml

a. 加密算法

b. PRF算法

c. Integrity算法

d. DH算法

e. ESN - Extended Sequence Numbers


#### 3. IPSec SA的协商内容

参考：http://www.iana.org/assignments/isakmp-registry

a. ESP加密算法或AH完整性算法

b. 加密模式

c. 认证算法

d. SA生存时间

e. 压缩算法

f. DH算法和参数

g. 加密密钥长度

h. 认证密钥长度

以上均有部分内容是可选的，不是所有的参数都必须协商。上面的三个链接里都详细描述了IANA对每个阶段SA协商用到的参数，比如算法的编号等等。
