---
layout: post
title: "tar 备份系统"
date: 2019-06-05 10:18:00 +0800
comments: false
categories:
- 2019
- 2019~06
- tools
- tools~ubuntu
tags:
---

#### tar备份系统

```
	tar -czpvf /baksys/backup.tgz  /  --exclude=/baksys --exclude=/lost+found --exclude=/proc/* --exclude=/sys/* --exclude=/tmp/* --exclude=/dev/* --exclude=/media/* --exclude=/mnt/*

	-p, --preserve-permissions, --same-permissions 解压文件权限信息(默认只为超级用户服务)。 
		此参数用于保持文件权限信息，是必要的。
	--exclude= 参数列出了不需要备份的目录，可以根据实际情况自行调整，
		注意：不要忘记备份/boot目录或分区，不要备份存储备份文件的目录。 
		特别注意：--exclude=后面的目录名使用绝对路径或相对路径的方式应与需备份目录的书写方式一致即要么都用绝对路径要么都使用相对路径，
		否则会导致exclude失效，而且目录一定不能使用/xxx/的形式，必须是/xxx/*或/xxx的形式，否则也失效。 
	注意，虽然/proc的目录下内容不可备份，但是/proc目录还是应该备份的，否则需要在迁移解压缩后手动mkdir增加/proc目录；
		其他几个亦同，但是/lost+found目录非linux系统需要，可全不备份。

```

#### 恢复系统
```
	mkfs.ext4 /dev/sda1
	mount /dev/sda1 /mnt/
	cd /mnt
	tar -xzpvf backup.tgz
```

#### 修复MBR、grub
从ubuntu u盘执行：
```
	mkdir /mnt
	mount /dev/sda1 /mnt
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

