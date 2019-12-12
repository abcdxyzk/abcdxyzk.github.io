---
layout: post
title: "ubuntu crash"
date: 2019-12-12 12:55:00 +0800
comments: false
categories:
- 2019
- 2019~12
- debug
- debug~kdump、crash
tags:
---

https://www.jianshu.com/p/3c92647140f7

https://help.ubuntu.com/lts/serverguide/kernel-crash-dump.html

#### 自己编译的内核会OOM，需要增大内存
If the dump does not work due to OOM (Out Of Memory) error, then try increasing the amount of reserved memory by editing 

/etc/default/grub.d/kdump-tools.cfg
```
	GRUB_CMDLINE_LINUX_DEFAULT="$GRUB_CMDLINE_LINUX_DEFAULT crashkernel=384M-:256M"
```
run sudo update-grub and then reboot afterwards, and then test again. 

-----------------------------------

#### 安装
```
	sudo apt-get install linux-crashdump
```
重启机器

#### 需要启动下面的服务
```
	$ service --status-all | grep ' k'
	[ + ] kdump-tools
	[ + ] kerneloops
	[ + ] kexec
	[ + ] kexec-load
```

#### 查看kdump的状态
```
	$ kdump-config show
	DUMP_MODE:        kdump
	USE_KDUMP:        1
	KDUMP_SYSCTL:     kernel.panic_on_oops=1
	KDUMP_COREDIR:    /var/crash
	crashkernel addr: 0x21000000
	   /var/lib/kdump/vmlinuz: symbolic link to /boot/vmlinuz-4.15.18
	kdump initrd: 
	   /var/lib/kdump/initrd.img: symbolic link to /var/lib/kdump/initrd.img-4.15.18
	current state:    ready to kdump
```

#### 验证

```
	echo 1 > /proc/sys/kernel/sysrq
	echo c > /proc/sysrq-trigger
```

