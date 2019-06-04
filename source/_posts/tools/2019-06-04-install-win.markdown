---
layout: post
title: "硬盘安装windows及MBR、grub修复"
date: 2019-06-04 21:12:00 +0800
comments: false
categories:
- 2019
- 2019~06
- tools
- tools~ubuntu
tags:
---

#### 硬盘安装win7

```
	mount -o loop win7.iso /mnt
	mount -t ntfs /dev/sda3 /tmp/kk
	cp -r /mnt/* /tmp/kk/
	umount /tmp/kk

	reboot

	https://askubuntu.com/questions/254491/failed-to-get-canonical-path-of-cow
	重启后来到 grub 的引导菜单，按 c 键进入命令行模式：
	grub> set root=(hd1,2)
	grub> insmod ntfs
	grub> ntldr /bootmgr
	grub> boot


```

注意：

0. 起因：从 u盘 安装极其缓慢，为什么？？？

1. 从 /dev/nvmessd 的grub进入没有 ntldr 命令 ？？？

2. 从 /dev/sda2 的grub进入有 ntldr 命令

3. 技嘉主板先制作usb启动盘，加入技嘉驱动 http://download.gigabyte.cn/FileList/Utility/mb_utility_windowsimagetool.zip 方法：http://www.gigabyte.cn/WebPage/-79/usb.html 。再从u盘 cp * /tmp/kk/


#### 修复MBR、grub
从ubuntu u盘执行：
```
	mkdir /mnt
	mount /dev/sda2 /mnt
	cd /mnt

	grub-install --recheck --root-directory=/mnt /dev/sda 

```

注意：

1. ubuntu系统的 grub-install 64位的不能执行？？？ u盘中的grub-install i386可以执行？？？

2. 临时进系统方法：
```
	insmod linux
	ls
	root=(hd0,1)
	linux /boot/vmlinuz root=/dev/sda1
	initrd /boot/initrd
	boot
```

```
	# win7
	set root=(hd0,1)
	chainloader +1
	boot
```

