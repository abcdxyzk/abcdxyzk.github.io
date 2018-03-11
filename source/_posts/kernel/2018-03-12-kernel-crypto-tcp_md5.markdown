---
layout: post
title: "TCP MD5选项"
date: 2018-03-12 02:58:00 +0800
comments: false
categories:
- 2018
- 2018~03
- kernel
- kernel~crypto
tags:
---

http://blog.csdn.net/u011130578/article/details/44942679

#### 8.5.1 选项功能

　　TCP MD5选项用于强化BGP协议的安全性，其基本原理是在TCP报文段的选项中携带MD5摘要。这个摘要的行为类似于这个报文的签名，其中包含这只有通信双方才能理解的信息。如果BGP协议使用TCP作为其传输层协议，使用MD5选项会有效减少安全隐患。

#### 8.5.2 协议规范

　　TCP MD5选项的规范由RFC 2385提出。

　　每一个TCP报文段都应该携带MD5选项（包含一个16字节的MD5 digest）。MD5算法的输入数据如下（严格按照顺序）：

（1）TCP伪首部（源IP，目的IP，填充0的协议号，报文长度）

（2）TCP首部，不包含选项，checksum计为0

（3）TCP数据段（如果有）

（4）密钥或口令，这个需要TCP通信双方和连接规范都知晓

　　接收方收到TCP报文时，必须根据报文的信息以及自己的密钥来计算digest，并与报文中的digest进行比较。如果比较失败则必须丢弃报文，并且不能产生任何响应。这样就大大增加了攻击者通过伪造TCP报文实施对BGP协议的攻击的难度。

#### 8.5.3 开启方法

　　Linux内核需要开启CONFIG_TCP_MD5SIG编译选项才能支持TCP MD5选项功能。应用进程还需要使用TCP_MD5SIG socket选项导入密钥：

```
    struct tcp_md5sig cmd;  
    ...  
    setsockopt(sockfd, SOL_TCP, TCP_MD5SIG,  &cmd, sizeof(cmd));  
```

　　其中struct tcp_md5sig的定义为：
```
    191 #define TCP_MD5SIG_MAXKEYLEN    80  
    192   
    193 struct tcp_md5sig {  
    194     struct __kernel_sockaddr_storage tcpm_addr; /* address associated */  
    195     __u16   __tcpm_pad1;                /* zero */  
    196     __u16   tcpm_keylen;                /* key length */  
    197     __u32   __tcpm_pad2;                /* zero */  
    198     __u8    tcpm_key[TCP_MD5SIG_MAXKEYLEN];     /* key (binary) */  
    199 };  
```
　　其中tcpm_addr是要通信的服务器的地址（IP地址、端口等），如果sockfd要与N个机器进行通信则需要调用N此setsockopt系统调用来导入相应的地址-密钥对。举个例子，如果A要与B通信，则A需要调用setsockopt来导入B的地址和一个密钥Key，而B也需要调用setsockopt来导入A的地址和与A相同的密钥Key，然后双方才能使用MD5选项进行通信。

#### 8.5.4 内核实现

　　TCP_MD5SIG socket选项对应的内核代码为：

```
    2371 static int do_tcp_setsockopt(struct sock *sk, int level,  
    2372         int optname, char __user *optval, unsigned int optlen)  
    2373 {  
    ...  
    2605 #ifdef CONFIG_TCP_MD5SIG  
    2606     case TCP_MD5SIG:  
    2607         /* Read the IP->Key mappings from userspace */  
    2608         err = tp->af_specific->md5_parse(sk, optval, optlen);　//指向tcp_v4_parse_md5_keys函数  
    2609         break;  
    2610 #endif  
    ...  
```

　　tcp_v4_parse_md5_keys用于导入MD5签名的密钥（key）：

```
    1083 static int tcp_v4_parse_md5_keys(struct sock *sk, char __user *optval,  
    1084                  int optlen)  
    1085 {    
    1086     struct tcp_md5sig cmd;  
    1087     struct sockaddr_in *sin = (struct sockaddr_in *)&cmd.tcpm_addr;  
    1088      
    1089     if (optlen < sizeof(cmd))  
    1090         return -EINVAL;  
    1091      
    1092     if (copy_from_user(&cmd, optval, sizeof(cmd)))  
    1093         return -EFAULT;  
    1094   
    1095     if (sin->sin_family != AF_INET)  
    1096         return -EINVAL;  
    1097   
    1098     if (!cmd.tcpm_key || !cmd.tcpm_keylen)  
    1099         return tcp_md5_do_del(sk, (union tcp_md5_addr *)&sin->sin_addr.s_addr,  
    1100                       AF_INET);　//删除key  
    1101      
    1102     if (cmd.tcpm_keylen > TCP_MD5SIG_MAXKEYLEN)  
    1103         return -EINVAL;  
    1104   
    1105     return tcp_md5_do_add(sk, (union tcp_md5_addr *)&sin->sin_addr.s_addr,  
    1106                   AF_INET, cmd.tcpm_key, cmd.tcpm_keylen,  
    1107                   GFP_KERNEL);  
    1108 }  
```

　　tcp_md5_do_add和tcp_md5_do_del用于添加和删除key：

```
     998 int tcp_md5_do_add(struct sock *sk, const union tcp_md5_addr *addr,  
     999            int family, const u8 *newkey, u8 newkeylen, gfp_t gfp)  
    1000 {  
    1001     /* Add Key to the list */  
    1002     struct tcp_md5sig_key *key;  
    1003     struct tcp_sock *tp = tcp_sk(sk);  
    1004     struct tcp_md5sig_info *md5sig;  
    1005   
    1006     key = tcp_md5_do_lookup(sk, addr, family);  
    1007     if (key) {　//如果有现成的  
    1008         /* Pre-existing entry - just update that one. */  
    1009         memcpy(key->key, newkey, newkeylen);　//更新之  
    1010         key->keylen = newkeylen;  
    1011         return 0;  
    1012     }  
    1013   
    1014     md5sig = rcu_dereference_protected(tp->md5sig_info,  
    1015                        sock_owned_by_user(sk));  
    1016     if (!md5sig) {  
    1017         md5sig = kmalloc(sizeof(*md5sig), gfp);  
    1018         if (!md5sig)  
    1019             return -ENOMEM;  
    1020   
    1021         sk_nocaps_add(sk, NETIF_F_GSO_MASK);  
    1022         INIT_HLIST_HEAD(&md5sig->head);  
    1023         rcu_assign_pointer(tp->md5sig_info, md5sig);  
    1024     }  
    1025   
    1026     key = sock_kmalloc(sk, sizeof(*key), gfp);  
    1027     if (!key)  
    1028         return -ENOMEM;  
    1029     if (hlist_empty(&md5sig->head) && !tcp_alloc_md5sig_pool(sk)) {  
    1030         sock_kfree_s(sk, key, sizeof(*key));  
    1031         return -ENOMEM;  
    1032     }  
    1033   
    1034     memcpy(key->key, newkey, newkeylen);　//导入密钥  
    1035     key->keylen = newkeylen;  
    1036     key->family = family;  
    1037     memcpy(&key->addr, addr,  
    1038            (family == AF_INET6) ? sizeof(struct in6_addr) :  
    1039                       sizeof(struct in_addr));　//导入地址信息  
    1040     hlist_add_head_rcu(&key->node, &md5sig->head);  
    1041     return 0;  
    1042 }  
    1043 EXPORT_SYMBOL(tcp_md5_do_add);  
    1044   
    1045 int tcp_md5_do_del(struct sock *sk, const union tcp_md5_addr *addr, int family)  
    1046 {  
    1047     struct tcp_sock *tp = tcp_sk(sk);  
    1048     struct tcp_md5sig_key *key;  
    1049     struct tcp_md5sig_info *md5sig;  
    1050   
    1051     key = tcp_md5_do_lookup(sk, addr, family);  
    1052     if (!key)  
    1053         return -ENOENT;  
    1054     hlist_del_rcu(&key->node);  
    1055     atomic_sub(sizeof(*key), &sk->sk_omem_alloc);  
    1056     kfree_rcu(key, rcu);  
    1057     md5sig = rcu_dereference_protected(tp->md5sig_info,  
    1058                        sock_owned_by_user(sk));  
    1059     if (hlist_empty(&md5sig->head))  
    1060         tcp_free_md5sig_pool();  
    1061     return 0;  
    1062 }  
```

　　在TCP发送数据前构建选项信息（tcp_syn_options、tcp_established_options、tcp_synack_options）时都会执行类似下面的代码：

```
         #ifdef CONFIG_TCP_MD5SIG  
     507     *md5 = tp->af_specific->md5_lookup(sk, sk);　//指向tcp_v4_md5_lookup  
     508     if (*md5) {  
     509         opts->options |= OPTION_MD5;  
     510         remaining -= TCPOLEN_MD5SIG_ALIGNED;  
     511     }  
     512 #else  
     513     *md5 = NULL;  
     514 #endif  
```

　　tcp_v4_md5_lookup用于查找MD5签名的key：

```
    949 struct tcp_md5sig_key *tcp_md5_do_lookup(struct sock *sk,  
    950                      const union tcp_md5_addr *addr,  
    951                      int family)  
    952 {  
    953     struct tcp_sock *tp = tcp_sk(sk);  
    954     struct tcp_md5sig_key *key;  
    955     unsigned int size = sizeof(struct in_addr);  
    956     struct tcp_md5sig_info *md5sig;  
    957   
    958     /* caller either holds rcu_read_lock() or socket lock */  
    959     md5sig = rcu_dereference_check(tp->md5sig_info,  
    960                        sock_owned_by_user(sk) ||  
    961                        lockdep_is_held(&sk->sk_lock.slock));  
    962     if (!md5sig)  
    963         return NULL;  
    964 #if IS_ENABLED(CONFIG_IPV6)  
    965     if (family == AF_INET6)  
    966         size = sizeof(struct in6_addr);  
    967 #endif  
    968     hlist_for_each_entry_rcu(key, &md5sig->head, node) {  
    969         if (key->family != family)  
    970             continue;     
    971         if (!memcmp(&key->addr, addr, size))　//地址匹配  
    972             return key;  
    973     }  
    974     return NULL;  
    975 }  
    976 EXPORT_SYMBOL(tcp_md5_do_lookup);  
    977   
    978 struct tcp_md5sig_key *tcp_v4_md5_lookup(struct sock *sk,  
    979                      struct sock *addr_sk)  
    980 {     
    981     union tcp_md5_addr *addr;  
    982       
    983     addr = (union tcp_md5_addr *)&inet_sk(addr_sk)->inet_daddr;  
    984     return tcp_md5_do_lookup(sk, addr, AF_INET);  
    985 }  
```

　　可见如果应用进程导入了key，在构建选项时就会找到。选项信息构建完毕后，tcp_options_write函数会将选项信息写入TCP报头中：

```
     409 static void tcp_options_write(__be32 *ptr, struct tcp_sock *tp,  
     410                   struct tcp_out_options *opts)  
     411 {  
     412     u16 options = opts->options;    /* mungable copy */  
     413   
     414     if (unlikely(OPTION_MD5 & options)) {  
     415         *ptr++ = htonl((TCPOPT_NOP << 24) | (TCPOPT_NOP << 16) |  
     416                    (TCPOPT_MD5SIG << 8) | TCPOLEN_MD5SIG);  
     417         /* overload cookie hash location */  
     418         opts->hash_location = (__u8 *)ptr;　//hash_location指向digest所在内存的首地址  
     419         ptr += 4;　//digest大小为16个字节  
     420     }  
     ...  
```

　　tcp_options_write并没有写入MD5 digest，这个工作在后面完成：
```
     828 static int tcp_transmit_skb(struct sock *sk, struct sk_buff *skb, int clone_it,  
     829                 gfp_t gfp_mask)  
     830 {  
    ...  
     870     if (unlikely(tcb->tcp_flags & TCPHDR_SYN))  
     871         tcp_options_size = tcp_syn_options(sk, skb, &opts, &md5);  
     872     else  
     873         tcp_options_size = tcp_established_options(sk, skb, &opts,  
     874                                &md5);  
    ...  
     925     tcp_options_write((__be32 *)(th + 1), tp, &opts);  
    ...  
     929 #ifdef CONFIG_TCP_MD5SIG  
     930     /* Calculate the MD5 hash, as we have all we need now */  
     931     if (md5) {  
     932         sk_nocaps_add(sk, NETIF_F_GSO_MASK);  
     933         tp->af_specific->calc_md5_hash(opts.hash_location,  
     934                            md5, sk, NULL, skb);　//指向tcp_v4_md5_hash_skb  
     935     }  
     936 #endif  
    ...  
```

　　tcp_v4_md5_hash_skb函数计算MD5 digest：

```
    1165 int tcp_v4_md5_hash_skb(char *md5_hash, struct tcp_md5sig_key *key,  
    1166             const struct sock *sk, const struct request_sock *req,  
    1167             const struct sk_buff *skb)  
    1168 {  
    1169     struct tcp_md5sig_pool *hp;  
    1170     struct hash_desc *desc;  
    1171     const struct tcphdr *th = tcp_hdr(skb);  
    1172     __be32 saddr, daddr;  
    1173   
    1174     if (sk) {  
    1175         saddr = inet_sk(sk)->inet_saddr;  
    1176         daddr = inet_sk(sk)->inet_daddr;  
    1177     } else if (req) {  
    1178         saddr = inet_rsk(req)->loc_addr;  
    1179         daddr = inet_rsk(req)->rmt_addr;  
    1180     } else {  
    1181         const struct iphdr *iph = ip_hdr(skb);  
    1182         saddr = iph->saddr;   
    1183         daddr = iph->daddr;  
    1184     }  
    1185   
    1186     hp = tcp_get_md5sig_pool();  
    1187     if (!hp)  
    1188         goto clear_hash_noput;  
    1189     desc = &hp->md5_desc;  
    1190   
    1191     if (crypto_hash_init(desc))  
    1192         goto clear_hash;  
    1193   
    1194     if (tcp_v4_md5_hash_pseudoheader(hp, daddr, saddr, skb->len))　//伪首部  
    1195         goto clear_hash;  
    1196     if (tcp_md5_hash_header(hp, th))　//TCP头  
    1197         goto clear_hash;  
    1198     if (tcp_md5_hash_skb_data(hp, skb, th->doff << 2))　//TCP数据  
    1199         goto clear_hash;  
    1200     if (tcp_md5_hash_key(hp, key))　//key  
    1201         goto clear_hash;  
    1202     if (crypto_hash_final(desc, md5_hash))　//将MD5 digest写入  
    1203         goto clear_hash;  
    1204   
    1205     tcp_put_md5sig_pool();  
    1206     return 0;  
    1207   
    1208 clear_hash:  
    1209     tcp_put_md5sig_pool();  
    1210 clear_hash_noput:  
    1211     memset(md5_hash, 0, 16);  
    1212     return 1;  
    1213 }  
```

　　TCP在收到报文时会在入口函数检查MD5选项：

```
    1800 int tcp_v4_do_rcv(struct sock *sk, struct sk_buff *skb)  
    1801 {  
    1802     struct sock *rsk;  
    1803 #ifdef CONFIG_TCP_MD5SIG  
    1804     /* 
    1805      * We really want to reject the packet as early as possible 
    1806      * if: 
    1807      *  o We're expecting an MD5'd packet and this is no MD5 tcp option 
    1808      *  o There is an MD5 option and we're not expecting one 
    1809      */  
    1810     if (tcp_v4_inbound_md5_hash(sk, skb))  
    1811         goto discard;  
    1812 #endif  
    ...  
```

　　tcp_v4_inbound_md5_hash函数返回false时检查通过：

```
    1216 static bool tcp_v4_inbound_md5_hash(struct sock *sk, const struct sk_buff *skb)  
    1217 {  
    1218     /* 
    1219      * This gets called for each TCP segment that arrives 
    1220      * so we want to be efficient. 
    1221      * We have 3 drop cases: 
    1222      * o No MD5 hash and one expected. 
    1223      * o MD5 hash and we're not expecting one. 
    1224      * o MD5 hash and its wrong. 
    1225      */  
    1226     const __u8 *hash_location = NULL;  
    1227     struct tcp_md5sig_key *hash_expected;  
    1228     const struct iphdr *iph = ip_hdr(skb);  
    1229     const struct tcphdr *th = tcp_hdr(skb);  
    1230     int genhash;  
    1231     unsigned char newhash[16];  
    1232   
    1233     hash_expected = tcp_md5_do_lookup(sk, (union tcp_md5_addr *)&iph->saddr,  
    1234                       AF_INET);　//根据源IP地址查找key  
    1235     hash_location = tcp_parse_md5sig_option(th);　//找到MD5 digest在TCP报头中的位置  
    1236   
    1237     /* We've parsed the options - do we have a hash? */  
    1238     if (!hash_expected && !hash_location)　//进程没有导入key信息且没有找到MD5选项  
    1239         return false;　//OK  
    1240   
    1241     if (hash_expected && !hash_location) { //进程导入了key信息且没有找到MD5选项  
    1242         NET_INC_STATS_BH(sock_net(sk), LINUX_MIB_TCPMD5NOTFOUND);  
    1243         return true;　//接收端期望有MD5选项而发送端没有，不行  
    1244     }  
    1245   
    1246     if (!hash_expected && hash_location) {　//进程没有导入key信息但找到了MD5选项  
    1247         NET_INC_STATS_BH(sock_net(sk), LINUX_MIB_TCPMD5UNEXPECTED);  
    1248         return true;　//接收端不期望有MD5选项而发送端有，也不行  
    1249     }  
    1250   
    1251     /* Okay, so this is hash_expected and hash_location - 
    1252      * so we need to calculate the checksum. 
    1253      */  
    1254     genhash = tcp_v4_md5_hash_skb(newhash,  
    1255                       hash_expected,  
    1256                       NULL, NULL, skb);　//使用key计算digest  
    1257   
    1258     if (genhash || memcmp(hash_location, newhash, 16) != 0) {　//生成digest失败或digest不一样则检查不通过，丢弃之  
    1259         net_info_ratelimited("MD5 Hash failed for (%pI4, %d)->(%pI4, %d)%s\n",  
    1260                      &iph->saddr, ntohs(th->source),  
    1261                      &iph->daddr, ntohs(th->dest),  
    1262                      genhash ? " tcp_v4_calc_md5_hash failed"  
    1263                      : "");  
    1264         return true;  
    1265     }  
    1266     return false;  
    1267 }  
```

　　tcp_parse_md5sig_option用于解析MD5选项：

```
    3635 #ifdef CONFIG_TCP_MD5SIG  
    3636 /*   
    3637  * Parse MD5 Signature option 
    3638  */           
    3639 const u8 *tcp_parse_md5sig_option(const struct tcphdr *th)  
    3640 {                       
    3641     int length = (th->doff << 2) - sizeof(*th);  
    3642     const u8 *ptr = (const u8 *)(th + 1);  
    3643   
    3644     /* If the TCP option is too short, we can short cut */  
    3645     if (length < TCPOLEN_MD5SIG)  
    3646         return NULL;  
    3647       
    3648     while (length > 0) {  
    3649         int opcode = *ptr++;  
    3650         int opsize;  
    3651   
    3652         switch(opcode) {  
    3653         case TCPOPT_EOL:  
    3654             return NULL;  
    3655         case TCPOPT_NOP:  
    3656             length--;  
    3657             continue;  
    3658         default:  
    3659             opsize = *ptr++;  
    3660             if (opsize < 2 || opsize > length)  
    3661                 return NULL;  
    3662             if (opcode == TCPOPT_MD5SIG)  
    3663                 return opsize == TCPOLEN_MD5SIG ? ptr : NULL;  
    3664         }  
    3665         ptr += opsize - 2;  
    3666         length -= opsize;  
    3667     }  
    3668     return NULL;  
    3669 }  
    3670 EXPORT_SYMBOL(tcp_parse_md5sig_option);  
    3671 #endif  
```

　　使用TCP MD5选项带来安全性的同时，由于需要计算MD5 digest会带来一些性能损耗，且每包都携带18字节的MD5选项字段也会降低数据发送效率。不过对于类似BGP这样对安全性要求较高的应用来说，这些代码应该是可以承受的。

