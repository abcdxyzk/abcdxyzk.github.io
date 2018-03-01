---
layout: post
title: "内核模块签名--命令行"
date: 2018-02-09 01:38:00 +0800
comments: false
categories:
- 2018
- 2018~02
- kernel
- kernel~signature
tags:
---

依据 scripts/sign-file, 命令行签名模块及验证签名

```
	# 生成签名，密匙MOK_private.perm; 证书MOK.crt; DER格式证书MOK.der
	openssl req -newkey rsa:4096 -nodes -keyout MOK_private.perm -new -x509 -sha512 -days 3650 -subj "/CN=my Machine Owner Key/" -out MOK.crt
	openssl x509 -outform DER -in MOK.crt -out MOK.der

	# 从密匙、证书提取公匙
	openssl rsa -in MOK_private.perm -pubout -out MOK_pub.perm
	openssl x509 -pubkey -in MOK.crt > MOK_pub.perm


	# 从ko中提取摘要
	openssl dgst -sha512 -binary test.ko.tmp > test.ko.sha512

	# 依据 scripts/sign-file, 需要在摘要前加些东西再做签名
	./a.out > test.ko.dgst
	cat test.ko.sha512 >> test.ko.dgst

	# 对摘要签名
	openssl rsautl -sign -in test.ko.dgst -out test.ko.sig -inkey MOK_private.key

	# 解密签名得到摘要
	openssl rsautl -verify -inkey MOK.crt -certin -in test.ko.sig -o test.ko.verify1
	openssl rsautl -verify -inkey MOK_pub.key -pubin -in test.ko.sig -o test.ko.verify2
	diff test.ko.verify1 test.ko.dgst

	# 直接用公匙验证签名
	openssl dgst -sha512 -verify MOK_pub.key -signature test.ko.sig test.ko.tmp
```

a.c
```
	#include <stdio.h>
	int main()
	{
		int i;
		char a[] = {    0x30, 0x51, 0x30, 0x0d, 0x06, 0x09,
				0x60, 0x86, 0x48, 0x01, 0x65, 0x03, 0x04, 0x02, 0x03,
				0x05, 0x00, 0x04, 0x40};

		for (i = 0; i < 19; i ++)
			printf("%c", a[i]);
		return 0;
	}
```

#### scripts/sign-file
```
	不同算法需要在摘要前加下面内容
	315 #
	316 # Digest the data
	317 #
	318 my $prologue;
	319 if ($dgst eq "sha1") {
	320     $prologue = pack("C*",
	321                      0x30, 0x21, 0x30, 0x09, 0x06, 0x05,
	322                      0x2B, 0x0E, 0x03, 0x02, 0x1A,
	323                      0x05, 0x00, 0x04, 0x14);
	324     $hash = 2;
	325 } elsif ($dgst eq "sha224") {
	326     $prologue = pack("C*",
	327                      0x30, 0x2d, 0x30, 0x0d, 0x06, 0x09,
	328                      0x60, 0x86, 0x48, 0x01, 0x65, 0x03, 0x04, 0x02, 0x04,
	329                      0x05, 0x00, 0x04, 0x1C);
	330     $hash = 7;
	331 } elsif ($dgst eq "sha256") {
	332     $prologue = pack("C*",
	333                      0x30, 0x31, 0x30, 0x0d, 0x06, 0x09,
	334                      0x60, 0x86, 0x48, 0x01, 0x65, 0x03, 0x04, 0x02, 0x01,
	335                      0x05, 0x00, 0x04, 0x20);
	336     $hash = 4;
	337 } elsif ($dgst eq "sha384") {
	338     $prologue = pack("C*",
	339                      0x30, 0x41, 0x30, 0x0d, 0x06, 0x09,
	340                      0x60, 0x86, 0x48, 0x01, 0x65, 0x03, 0x04, 0x02, 0x02,
	341                      0x05, 0x00, 0x04, 0x30);
	342     $hash = 5;
	343 } elsif ($dgst eq "sha512") {
	344     $prologue = pack("C*",
	345                      0x30, 0x51, 0x30, 0x0d, 0x06, 0x09,
	346                      0x60, 0x86, 0x48, 0x01, 0x65, 0x03, 0x04, 0x02, 0x03,
	347                      0x05, 0x00, 0x04, 0x40);
	348     $hash = 6;
	349 } else {
	350     die "Unknown hash algorithm: $dgst\n";
	351 }
	352
	353 my $signature;
	354 if ($signature_file) {
	355         $signature = read_file($signature_file);
	356 } else {
	357         #
	358         # Generate the digest and read from openssl's stdout
	359         #
	360         my $digest;  # 先算摘要
	361         $digest = readpipe("openssl dgst -$dgst -binary $module") || die "openssl dgst";
	362 
	363         #
	364         # Generate the binary signature, which will be just the integer that
	365         # comprises the signature with no metadata attached.
	366         #
	367         my $pid;     # 签名命令，签名的输入372行
	368         $pid = open2(*read_from, *write_to,
	369                      "openssl rsautl -sign -inkey $private_key -keyform PEM") ||
	370             die "openssl rsautl";
	371         binmode write_to; # 签名的输入是 $prologue . $digest
	372         print write_to $prologue . $digest || die "pipe to openssl rsautl";
	373         close(write_to) || die "pipe to openssl rsautl";
	374 
	375         binmode read_from;
	376         read(read_from, $signature, 4096) || die "pipe from openssl rsautl";
	377         close(read_from) || die "pipe from openssl rsautl";
	378         waitpid($pid, 0) || die;
	379         die "openssl rsautl died: $?" if ($? >> 8);
	380 }
	381 $signature = pack("n", length($signature)) . $signature,
	382 
```

----------------

https://www.jianshu.com/p/215eee5dbb05

整篇文章经由对[Signing Kernel Moudles For Security Boot][1]实践整理而成。如果能看懂原版的话，建议看该网页

[1]: https://access.redhat.com/documentation/en-US/Red_Hat_Enterprise_Linux/7/html/System_Administrators_Guide/sect-signing-kernel-modules-for-secure-boot.html "RedHat的建议"

在我们安装一个自己编译的模块包后，需要modprobe xx 然而，可能出现required key not available这样的提示。

这是由于采用EFI的Linux系统限制只有通过签名的模块才能加载运行。如果你是安装自己编译的模块，就需要自己签名了。

#### 1.需要安装依赖的工具：
```
	sudo yum install openssl
	sudo yum install kernel-devel
	sudo yum install perl
	sudo yum install mokutil
	sudo yum install keyutils
```

#### 2.对于System Key Rings的解释：

咱们的X.509 Keys放在哪儿呢？请看下表
```
	Source of X.509 Keys		User Ability to Add Keys	Keys Loaded During Boot
	UEFI Secure Boot "db"		Limited				.system_keyring
	UEFI Secure Boot "dbx"		Limited				.system_keyring
	Machine Owner Key (MOK) list	Yes				.system_keyring
```

密钥要经过系统验证，也就是说咱们的一对密钥中的公钥要加载进MOK中

#### 3.检查自己是否是EFI

```
	sudo keyctl list %:.system_keyring
```

你看到的就是MOK list

如果是EFI，你可以看到包含 EFI 字样的keyring。咱们在安装过程中，也要把自己的keyring也加到里面去。

#### 4.生成自己的密钥对

生成密钥配置文件
```
	sudo cat << EOF > configuration_file.config
	[ req ]
	default_bits = 4096
	distinguished_name = req_distinguished_name
	prompt = no
	string_mask = utf8only
	x509_extensions = myexts

	[ req_distinguished_name ]
	O = <你的签名key的名字>
	emailAddress = <你的E-mail>

	[ myexts ]
	basicConstraints=critical,CA:FALSE
	keyUsage=digitalSignature
	subjectKeyIdentifier=hash
	authorityKeyIdentifier=keyid
	EOF
```

你的名字和E-mail地址这些东西是为了标识你的签名密钥，毕竟是自己做的作品嘛。你还可以在* [req_distinguished_name] *部分添加更多信息，也可以删减。

生成密钥
```
	sudo openssl req -x509 -new -nodes -utf8 -sha256 -days 36500	\
		-batch -config configuration_file.config -outform DER	\
		-out public_key.der					\  
		-keyout private_key.priv
```

#### 5.登记你的公钥

公钥要登记在MOK list里

Centos7、RedHat EL7系系统,可以使用mokutil
```
	sudo mokutil --import my_signing_key_pub.der
```
这时系统会要你为MOK登记设置一个密码

设置完密码后，重启:

sudo reboot```

重启过程中会进入EFI的确认界面，输入刚刚设置的密码，一直确认就行

重启后，输入
```
	sudo keyctl list %:.system_keyring
```

你会发现MOK list比以前多了一项，也就是你的签名

#### 6.给你的模块签名

这里我结合我自己给wl模块签名的实例

这里 我的wl模块 来源于我安装了一个叫wl-kmod的包，这是无线网卡驱动，为了找到模块位置，我先输入：
```
	rpm -ql kmod-wl
```

找到了wl.ko的位置在/lib/modules/3.10.0-514.10.2.el7.x86_64/extra/wl/wl.ko

如果能给安装包直接签名貌似更好，但是我是已经安装完才进行补救的

那么就是给wl.ko签名啦:

```
	sudo perl /usr/src/kernels/$(uname -r)/scripts/sign-file \
		sha256 \
		/home/feyan/feyan_signing_key_pub.der\     #公钥文件（位置和名称视具体情况）
		/home/fayan/feyan_signing_key.priv\        #私钥文件（位置和名称视具体情况）
		/lib/modules/3.10.0-514.10.2.el7.x86_64/extra/wl/wl.ko   #模块文件
```

签名成功后，输入

sudo modprobe wl

**载入模块没有问题，说明我的签名成功了**

