---
layout: post
title: "ubuntu 16.04 & 18.04"
date: 2019-08-21 11:25:00 +0800
comments: false
categories:
- 2019
- 2019~08
- system
- system~ubuntu
tags:
---

#### 代理设置
```
	# cat /etc/apt/apt.conf
	http_proxy=http://proxy.proxy.com:8080
	https_proxy=http://proxy.proxy.com:8080

	# cat /etc/environment
	http_proxy=http://proxy.proxy.com:8080
	https_proxy=http://proxy.proxy.com:8080
	no_proxy=localhost,127.0.0.0/8,::1,*.pp.com,*.oa.com

	# cat /etc/systemd/system/docker.service.d/http-proxy.conf
	[Service]
	Environment="HTTP_PROXY=http://proxy.proxy.com:8080"
```

#### amdgpu.dc
VGA,DIV-D接口 4.15.0 以后内核黑屏，需要设置amdgpu.dc=0
HDMI,DP接口支持音频，需要设置amdgpu.dc=1

#### 注释掉下面这行将会显示引导菜单
```
	#GRUB_HIDDEN_TIMEOUT=0
```

#### 设定默认启动项 /etc/default/grub
```
	# 用数字
	GRUB_DEFAULT=0

	# 最近启动
	GRUB_DEFAULT=saved
	GRUB_SAVEDEFAULT=true

	# 指定内核
	GRUB_DEFAULT="gnulinux-advanced-999d2fc9-3d7b-4654-a25c-4f5d4472a23b>gnulinux-4.15.0-55-generic-advanced-999d2fc9-3d7b-4654-a25c-4f5d4472a23b"
```
Warning: Please don't use old title `Ubuntu, with Linux 4.15.0-55-generic' for GRUB_DEFAULT, use `Advanced options for Ubuntu>Ubuntu, with Linux 4.15.0-55-generic' (for versions before 2.00) or `gnulinux-advanced-999d2fc9-3d7b-4654-a25c-4f5d4472a23b>gnulinux-4.15.0-55-generic-advanced-999d2fc9-3d7b-4654-a25c-4f5d4472a23b' (for 2.00 or later)


#### 卸载amazon
```
	# 16.04
	sudo apt-get remove unity-webapps-common

	# 18.04
	sudo apt-get remove ubuntu-web-launchers
```

#### 新立得
```
	sudo apt-get install synaptic
```

#### 禁用apport
```
	/etc/default/apport
	enabled=0
```

#### 禁用service
```
	# 禁用
	sudo systemctl disable apport.service

	# 如果这不起作用，那么您需要屏蔽该服务
	systemctl mask apport.service

	# 重新启用
	systemctl unmask apport.service # if you masked it
	sudo systemctl enable apport.service
```

#### 中文输入法
```
	sudo apt-get install ibus-pinyin
	# 选择全拼模式，同时勾选“简拼”, 然后运行
	sudo ibus restart
```

#### 温度
```
	sudo apt-get install lm-sensors hddtemp
	sudo sensors-detect
	sensors
	sudo apt-get install psensor
```

#### 查看SSD状态
https://www.cnblogs.com/fiberhome/p/8275961.html
```
	hdparm -t --direct /dev/sda

	smartctl -i /dev/sda

	sudo smartctl -data -A /dev/sda
	sudo smartctl -A /dev/sda

	# 233一行的值就是寿命，默认为100，当小于10的时候就要非常注意了。

	/etc/fstab
	增加 noatime
```

#### nvme硬盘温度
```
	sudo apt-get install nvme-cli
	sudo nvme list

	sudo nvme smart-log /dev/nvme0
	sudo watch -n 1 nvme smart-log /dev/nvme0

	sudo nvme smart-log /dev/nvme0 | grep "^temperature"
```

#### SSD
```
	Aggressive LPM Support功能是SATA口的节能电源管理，开启会导致SSD掉盘，不认盘，掉速等问题。

	解决方法：

	到BIOS中找到Aggressive LPM Support并关闭。
```

#### 18.04 unity
```
	sudo apt install ubuntu-unity-desktop
	select lightdm

	# 恢复gnome
	sudo apt purge ubuntu-unity-desktop
	sudo dpkg-reconfigure gdm3
```

#### 18.04 用synergy1.6.2

https://packages.ubuntu.com/xenial/libcrypto++9v5

https://packages.ubuntu.com/xenial/synergy

[synergy_1.6.2-0ubuntu2_amd64.deb](/download/system/synergy_1.6.2-0ubuntu2_amd64.deb)

[libcrypto++9v5_5.6.1-9ubuntu0.1_amd64.deb](/download/system/libcrypto++9v5_5.6.1-9ubuntu0.1_amd64.deb)

