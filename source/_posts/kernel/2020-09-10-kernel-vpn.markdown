---
layout: post
title: "vpn"
date: 2020-09-10 04:39:00 +0800
comments: false
categories:
- 2020
- 2020~09
- kernel
- kernel~net
tags:
---

### 方案一

client ----- xor;drop ----> hongkong(squid) -----------> server

### 方案二

client ----- xor;drop ----> guangzhou(squid) ----- xor;drop;icmp+mss -----> hongkong(squid) ---------> server


[squid](/blog/cats/tools~squid/)

[icmp](/blog/2020/07/09/kernel-tcp-icmp/)

有些时候client和hongkong之间能够ping通，但是tcp就是不通，全被丢弃了，这时需要方案二。

client 和 squid 之间不好使用icmp，因为client大都是在NAT后面，主动发起的icmp能通，但被动的请求进不来。这时需要加上guangzhou中间节点

[mss](/blog/2020/07/09/kernel-adjust-mss/)

为了加icmp头，需要减少TCP_MSS。

[xor](/blog/2020/09/10/kernel-digest-xor/)

drop: 丢弃异常reset、fin等包。


### 通过web，实现跨平台随时随地购买使用

#### 购买CVM

[脚本购买CVM](/blog/2019/12/12/buy-cvm/)

web购买CVM:

```
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<meta name="viewport" content="width=device-width,minimum-scale=1.0,maximum-scale=1.0,user-scalable=no"/>
	<meta name="MobileOptimized" content="320">
	<meta name="format-detection" content="telephone=no">
	<meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">

	<!DOCTYPE html>
	<html>
	<head>
		<title>proxy</title>
	</head>
	<body>

	<?php
		$region = "hk";
		if (isset($_GET['region']))
			$region = $_GET['region'];
		$count = "0";
		if (isset($_GET['count']))
			$count = $_GET['count'];
	?>
	<form action="index.php" method="get">
	<input name="region" value="<?php echo $region; ?>" style="width:100px">
	<input name="count" value="0" style="width:100px">
	<input class="submit" type="submit" value="创建">
	</form>
	<?php
		$cmd = "python CVM.py $region $count";

		echo date("Y-m-d H:i:s") . " " . $cmd;
		echo "</br><br>";

		exec($cmd);

		if (filesize("show.log") > 0) {
			$myfile = fopen("show.log", "r");
			echo fread($myfile, filesize("show.log"));
			fclose($myfile);
		}
	?>
	</body>
	</html>
```

#### web配置cache_peer, 方案二中的guangzhou才需要

cachemgr.cgi 没找到接口，可惜

##### 填写 IP

```
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<meta name="viewport" content="width=device-width,minimum-scale=1.0,maximum-scale=1.0,user-scalable=no"/>
	<meta name="MobileOptimized" content="320">
	<meta name="format-detection" content="telephone=no">
	<meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">

	<!DOCTYPE html>
	<html>
	<head>
		<title>squid</title>
	</head>
	<body>

	<?php
		$up = "";
		if (isset($_GET['up']))
			$up = $_GET['up'];
	?>
	<form action="squid.php" method="get">
	<input name="up" value="<?php echo $up; ?>" style="width:200px">
	<input class="submit" type="submit" value="更新">
	</form>
	<?php

		echo date("Y-m-d H:i:s");
		echo "</br><br>";

		if (strlen($up) > 3 && strlen($up) < 128) {
			$myfile = fopen("squid.log", "w");
			fwrite($myfile, $up . " up.com\n");
			fclose($myfile);
		}
		$myfile = fopen("squid.log", "r");
		echo fread($myfile, filesize("squid.log"));
		fclose($myfile);
	?>
	</body>
	</html>
```

##### 刷新squid

```
	# */1 * * * * cd /var/www/html/; /var/www/html/squid.sh
	# */1 * * * * sleep 30; cd /var/www/html/; /var/www/html/squid.sh
	# chown -R www-data:www-data ../html/
	cat /etc/hosts | grep up.com > hosts_tmp
	diff hosts_tmp squid.log > /dev/null
	if [ $? -eq 0 ]; then exit; fi

	cat /etc/hosts | grep -v up.com > hosts
	cat squid.log >> hosts 
	cp hosts /etc/

	/usr/sbin/squid -k reconfigure -f /root/squid/squid.conf
```
