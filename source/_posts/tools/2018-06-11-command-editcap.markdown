---
layout: post
title: "editcap: pcap文件的合并和分隔"
date: 2018-06-11 02:49:00 +0800
comments: false
categories:
- 2018
- 2018~06
- tools
- tools~command
tags:
---

#### centos7 editcap 半静态编译

https://www.wireshark.org/download/src/wireshark-2.6.1.tar.xz

ftp://ftp.icm.edu.pl/vol/rzm6/linux-oracle-repo/OracleLinux/OL7/latest/SRPMS/libpcap-1.5.3-11.el7.src.rpm

[editcap_el7](/download/tools/editcap_el7.tar)

[mergecap_el7](/download/tools/mergecap_el7.tar)

[libcap.a](/download/tools/libpcap.a.tar)

```
	./configure CFLAGS=-static
	make CFLAGS=-static


	# CFLAGS=-static 不能完全起作用，
	# 通过在 ./libtool 中增加 set -x 后得知 editcap 的链接命令，修改后如下
	gcc -std=gnu99 -Wall -Wextra -Wendif-labels -Wpointer-arith -Wformat-security -fwrapv -fno-strict-overflow -fno-delete-null-pointer-checks -Wvla -Waddress -Wattributes -Wdiv-by-zero -Wignored-qualifiers -Wpragmas -Wno-overlength-strings -Wno-long-long -Wc++-compat -Wshadow -Wno-pointer-sign -Wold-style-definition -Wstrict-prototypes -Wlogical-op -Wjump-misses-init -Werror=implicit -fexcess-precision=fast -fvisibility=hidden -Wl,-Bstatic -o editcap editcap-editcap.o editcap-version_info.o -pthread -Wl,-Bstatic ui/libui.a wiretap/.libs/libwiretap.a /usr/local/wireshark/wireshark-2.6.1/wsutil/.libs/libwsutil.a wsutil/.libs/libwsutil.a -lgnutls -lgthread-2.0 -lgmodule-2.0 -lglib-2.0 -lgcrypt -lgpg-error -lz -lm -pthread -Wl,-Bdynamic -lgcc_s -ldl

```

[glib2-2.42.2-5.el7.src.rpm](https://buildlogs.cdn.centos.org/c7.1511.00/glib2/20151120104129/2.42.2-5.el7.x86_64/glib2-2.42.2-5.el7.src.rpm)  
[libgcrypt-1.5.3-12.el7_1.1.src.rpm](https://buildlogs.centos.org/c7.01.u/libgcrypt/20150512144559/1.5.3-12.el7_1.1.x86_64/libgcrypt-1.5.3-12.el7_1.1.src.rpm)  
[libgpg-error-1.12-3.el7.src.rpm](http://vault.centos.org/7.3.1611/os/Source/SPackages/libgpg-error-1.12-3.el7.src.rpm)  
[gnutls-3.3.8-14.el7_2.src.rpm](https://buildlogs.centos.org/c7.1511.u/gnutls/20160107182235/3.3.8-14.el7_2.x86_64/gnutls-3.3.8-14.el7_2.src.rpm)  

以上部分需要 make CFLAGS=-static 才能生成*.a

[wireshark-1.10.14-7.el7.src.rpm](ftp://ftp.icm.edu.pl/vol/rzm6/linux-slc/centos/7.2.1511/os/Source/SPackages/wireshark-1.10.14-7.el7.src.rpm)

wireshark-1.10.14-7.el7.src.rpm 的`./configure CFLAGS=-static`和`make CFLAGS=-static`过不了，无法使用。。。

----------------------------

http://qwxingren.blog.sohu.com/304463885.html

#### 拆分

使用wireshark自带的editcap。我们的系统Centos 5.8，执行 yum install wireshark，就已经安装了editcap。

##### 1. 根据时间来拆分，利用-A 起始时间和-B 截止时间来提去某个时间段的数据。

用法：editcap -A <起始时间>  -B <截止时间>  <源文件名> <目的文件名>

示例：
```
	editcap -A "2014-07-12 12:55:00" -B "2014-07-12 12:56:00" eth0-rtp.cap  out_rtp.cap
```

##### 2.按packge数量拆分为多个文件
用法：editcap -c <每个文件的包数> <源文件名> <目的文件名>

示例：
```
	editcap -c 100 dump.pcap test.pcap
```

#### 合并

在wireshark中通过filter过滤出sip信令，但是在多个文件中,megecap可以将多个pcap文件合并为一个文件。

用法：mergecap -w <输出文件>  <源文件1>  <源文件2> ...

示例：
```
	mergecap -w compare.pcap a.pcap b.pcap
```

