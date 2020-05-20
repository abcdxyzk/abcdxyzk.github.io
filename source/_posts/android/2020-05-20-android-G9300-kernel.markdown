---
layout: post
title: "G9300 kernel"
date: 2020-05-20 11:34:00 +0800
comments: false
categories:
- 2020
- 2020~05
- android
- android~G9300
tags:
---

#### 编译

https://opensource.samsung.com/uploadSearch?searchValue=G9300

https://opensource.samsung.com/uploadSearch?searchValue=G9350

较新的ROM没刷成功（8.0.0 BL锁了？？？），选择 7.0 ROM G9300ZCU2BRD1。

G9300公开的内核最接近的是G9300ZCU2BQI3，但G9350公开的G9350ZCU2BQK3内核更接近G9300ZCU2BRD1，但需要复制G9300的 arch/arm64/boot/dts/samsung/ 到 G9350的arch/arm64/boot/dts/samsung/

参造 build_kernel.sh 编译

编译器用 [android-ndk-r20b-linux-x86_64.zip](https://developer.android.google.cn/ndk/downloads/older_releases?hl=zh-cn) 中的 aarch64-linux-android-4.9。也可以用这个 tools/prebuilts/gcc-cfp-jopp-only/aarch64-linux-android-4.9/ ？？？

https://github.com/abcdxyzk/aarch64-linux-android-4.9 从 android-ndk-r20b-linux-x86_64.zip 提取的 aarch64-linux-android-4.9

修复wifi目录，它的写法是需要需要获取android版本，我们默认就是 7
```
	diff --git a/drivers/net/wireless/bcmdhd4359/Makefile b/drivers/net/wireless/bcmdhd4359/Makefile
	index 9acd0726..433bb7b1 100755
	--- a/drivers/net/wireless/bcmdhd4359/Makefile
	+++ b/drivers/net/wireless/bcmdhd4359/Makefile
	@@ -271,16 +271,18 @@ FOUND_VERSION_PATH := $(foreach dir,$(CANDIDATE_VERSION_PATH), $(wildcard $(dir)
	 FOUND_VERSION_PATH := $(word 1, $(FOUND_VERSION_PATH))
	 ifeq ($(FOUND_VERSION_PATH),)
	 $(warning Not found Android version file. Set as Legacy mode)
	-DHDCFLAGS += -DDHD_LEGACY_FILE_PATH
	-DHDCFLAGS += -DDHD_DISABLE_ANDROID_FEATURE_SET
	+#DHDCFLAGS += -DDHD_LEGACY_FILE_PATH
	+#DHDCFLAGS += -DDHD_DISABLE_ANDROID_FEATURE_SET
	+DHDCFLAGS += -DDHD_SET_COUNTRY_SUPPORT
	 else
	 # Extract version string and get major number
	 ANDROID_PLATFORM_VERSION := $(shell grep "PLATFORM_VERSION := " $(FOUND_VERSION_PATH) | cut -d "=" -f 2 | cut -d "." -f 1 | sed 's/ //g')
	 $(warning Android Platform Version : $(ANDROID_PLATFORM_VERSION))
	 # If Android version lower than 7(Nougat) => Use Legacy File path
	 ifeq ($(shell expr $(ANDROID_PLATFORM_VERSION) \< 7),1)
	-DHDCFLAGS += -DDHD_LEGACY_FILE_PATH
	-DHDCFLAGS += -DDHD_DISABLE_ANDROID_FEATURE_SET
	+#DHDCFLAGS += -DDHD_LEGACY_FILE_PATH
	+#DHDCFLAGS += -DDHD_DISABLE_ANDROID_FEATURE_SET
	+DHDCFLAGS += -DDHD_SET_COUNTRY_SUPPORT
	 $(warning Will be use Legacy file path)
	 else
	 DHDCFLAGS += -DDHD_SET_COUNTRY_SUPPORT
```

编译后用到
```
	out/arch/arm64/boot/Image.gz

	$ find out/ -name '*.ko'
	out/drivers/gator/gator.ko
	out/drivers/scsi/ufs/ufs_test.ko
	out/drivers/input/evbug.ko
	out/drivers/spi/spidev.ko
	out/drivers/mmc/card/mmc_block_test.ko
	out/drivers/mmc/card/mmc_test.ko
	out/drivers/char/rdbg.ko
	out/block/test-iosched.ko
	out/net/ipv4/tcp_westwood.ko
	out/net/ipv4/tcp_htcp.ko
	out/net/bridge/br_netfilter.ko
```

#### 制作img

https://github.com/abcdxyzk/android_system_core

https://github.com/abcdxyzk/BootTools

```
	$ ~/kk/BootTools/hdrboot boot.img
	Magic: ANDROID!
	Kernel size: 0x9D203F (10297407)
	  Aligned size: 0x9D3000
	Kernel addr: 0x80008000
	Ramdisk size: 0x484ED0 (4738768)
	Ramdisk addr: 0x82200000
	Second size: 0x0 (0)
	Second addr: 0x80F00000
	Tags addr: 0x82000000
	Page size: 0x1000 (4096)
	Name: RILPA13A000KU
	Cmdline: console=null androidboot.hardware=qcom user_debug=31 msm_rtb.filter=0x37 ehci-hcd.park=3 lpm_levels.sleep_disabled=1 cma=24M@0-0xffffffff rcupdate.rcu_expedited=1
```

```
	$ ~/kk/android_system_core/mkbootimg/unpackbootimg -i boot.img
	Android magic found at: 0
	BOARD_KERNEL_CMDLINE console=null androidboot.hardware=qcom user_debug=31 msm_rtb.filter=0x37 ehci-hcd.park=3 lpm_levels.sleep_disabled=1 cma=24M@0-0xffffffff rcupdate.rcu_expedited=1
	BOARD_KERNEL_BASE 00008000
	BOARD_RAMDISK_OFFSET 02200000
	BOARD_SECOND_OFFSET 00f00000
	BOARD_TAGS_OFFSET 02000000
	BOARD_PAGE_SIZE 4096
	BOARD_SECOND_SIZE 0
	BOARD_DT_SIZE 7122944
```

替换 boot.img-zImage，cp out/arch/arm64/boot/Image.gz boot.img-zImage，然后重新制作 boot.img

```
	$ ~/kk/android_system_core/mkbootimg/mkbootimg --kernel boot.img-zImage --ramdisk boot.img-ramdisk.gz --base 0x80000000 --ramdisk_offset 0xFF8000 --pagesize 4096 --cmdline "console=null androidboot.hardware=qcom user_debug=31 msm_rtb.filter=0x37 ehci-hcd.park=3 lpm_levels.sleep_disabled=1 cma=24M@0-0xffffffff rcupdate.rcu_expedited=1" --ramdisk_offset 0x2200000 --board RILPA13A003KU --tags_offset 0x2000000 --dt boot.img-dt -o my_boot.img

	$ echo -n "SEANDROIDENFORCE" >> my_boot.img  # 解决开机出现 Kernel is not Seandroid Enforcing，https://tricksempire.com/kernel-is-not-seandroid-enforcing-android/

	$ lz4 -B6 boot.img  # 可选 https://stackoverflow.com/questions/58517762/odin-fail-lz4-is-invalid
```

[G9300 ROM包相关及降级原理-BL](/blog/2020/05/20/android-G9300-rom/)


#### 修改内核

```
	$ diff arch/arm64/configs/hero2qlte_chn_open_defconfig out/.config
	3c3
	< # Linux/arm64 3.18.31 Kernel Configuration 
	---
	> # Linux/arm64 3.18.31-13341302 Kernel Configuration
	325,336c325,337
	< # CONFIG_SEC_HEROQLTE_PROJECT is not set   
	< CONFIG_SEC_HERO2QLTE_PROJECT=y
	< # CONFIG_MACH_HERO2QLTE_ATT is not set
	< CONFIG_MACH_HERO2QLTE_CHNZC=y
	< # CONFIG_MACH_HERO2QLTE_SPR is not set
	< # CONFIG_MACH_HERO2QLTE_TMO is not set
	< # CONFIG_MACH_HERO2QLTE_USC is not set
	< # CONFIG_MACH_HERO2QLTE_VZW is not set
	< # CONFIG_MACH_HERO2QLTE_DCM is not set
	< # CONFIG_MACH_HERO2QLTE_KDI is not set
	< # CONFIG_MACH_HERO2QLTE_SED is not set
	< # CONFIG_MACH_HERO2QLTE_SINGLE is not set  
	---
	> CONFIG_SEC_HEROQLTE_PROJECT=y
	> # CONFIG_MACH_HEROQLTE_ACG is not set
	> # CONFIG_MACH_HEROQLTE_ATT is not set
	> CONFIG_MACH_HEROQLTE_CHNZC=y
	> # CONFIG_MACH_HEROQLTE_DCM is not set
	> # CONFIG_MACH_HEROQLTE_KDI is not set
	> # CONFIG_MACH_HEROQLTE_SPR is not set
	> # CONFIG_MACH_HEROQLTE_TMO is not set
	> # CONFIG_MACH_HEROQLTE_USC is not set
	> # CONFIG_MACH_HEROQLTE_VZW is not set
	> # CONFIG_MACH_HEROQLTE_MTR is not set
	> # CONFIG_MACH_HEROQLTE_SED is not set
	> # CONFIG_SEC_HERO2QLTE_PROJECT is not set
	582c583
	< CONFIG_RKP_CFP=y
	---
	> # CONFIG_RKP_CFP is not set
	584,585c585,586
	< CONFIG_RKP_CFP_JOPP=y
	< CONFIG_RKP_CFP_JOPP_MAGIC=0x00be7bad
	---
	> # CONFIG_RKP_CFP_JOPP is not set
	> CONFIG_RKP_CFP_JOPP_MAGIC=0xb3ea3bad
	592,595c593
	< CONFIG_TIMA_RKP=y
	< CONFIG_RKP_KDP=y
	< CONFIG_RKP_NS_PROT=y
	< CONFIG_RKP_DMAP_PROT=y
	---
	> # CONFIG_TIMA_RKP is not set
	1243c1241
	< CONFIG_KNOX_KAP=y
	---
	> # CONFIG_KNOX_KAP is not set
	1431d1428
	< CONFIG_DM_BUFIO=y
	1445,1446c1442
	< CONFIG_DM_VERITY=y
	< CONFIG_DM_VERITY_FEC=y
	---
	> # CONFIG_DM_VERITY is not set
	4026,4032c4022,4024
	< CONFIG_TIMA_RKP_L1_TABLES=y
	< CONFIG_TIMA_RKP_L2_TABLES=y
	< CONFIG_TIMA_RKP_LAZY_MMU=y
	< # CONFIG_TIMA_RKP_DEBUG is not set
	< CONFIG_TIMA=y
	< CONFIG_TIMA_LKMAUTH=y
	< CONFIG_TIMA_LKMAUTH_CODE_PROT=y
	---
	> # CONFIG_TIMA is not set
	> # CONFIG_TIMA_LKMAUTH is not set
	> # CONFIG_TIMA_LKMAUTH_CODE_PROT is not set 
	4034d4025
	< CONFIG_TIMA_UEVENT=y
```

#### 模块警告

内核比较严格，未使用变量都是ERROR
```
	EXTRA_CFLAGS += -g -Wno-unused-function -Wno-unused-variable
```

