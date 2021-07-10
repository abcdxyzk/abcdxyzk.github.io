---
layout: post
title: "企业微信重建部门并移动员工"
date: 2021-07-10 10:52:00 +0800
comments: false
categories:
- 2021
- 2021~07
- tools
- tools~wxwork
tags:
---

先将员工移动到临时部门中，删除所有部门，再重建部门，移动员工

https://work.weixin.qq.com/api/doc/90000/90135/90204

```
	<?php
	
		function queryUrl($url, $header, $postfields)
		{   
			if (function_exists('curl_init') != 1)
				throw new Exception("Please install curl plugin", 1); //请安装curl插件！
	
			$curl = curl_init();
			curl_setopt($curl, CURLOPT_URL, $url);
			curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
			curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1); 
			curl_setopt($curl, CURLOPT_TIMEOUT, 5); 
			curl_setopt($curl, CURLOPT_POST, 1); 
			curl_setopt($curl, CURLOPT_POSTFIELDS, $postfields);
	//		curl_setopt($curl, CURLOPT_USERAGENT, 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:68.0) Gecko/20100101 Firefox/68.0');
			$result = curl_exec($curl);
			curl_close($curl);
	
			return $result;
		}
	
		// TODO
		$app_access_token = '';
		$header = array('Content-Type: application/json');
	
		$fp = fopen('bmh_db', 'r');
		while (!feof($fp)) {
			$line = fgets($fp);
			if ($line == false)
				break;
			$line = explode(' ', trim($line));
	
			// 创建部门
			$msg = array(
				'id' => $line[0],
				'name' => $line[1], 
				'parentid' => $line[2],
				'order' => $line[3]
			);
			print $line[0];
			echo "\n";
			print(queryUrl("https://qyapi.weixin.qq.com/cgi-bin/department/create?access_token=$app_access_token", $header, json_encode($msg)));
			echo "\n";
	
			// 移动员工到部门
			$order = 1000000;
			for ($i = 4; $i < count($line); $i ++) {
				echo ' ', $line[$i];
				$order -= 100;
				$msg2 = array(
					'userid' => $line[$i],
					'department' => array($line[0]),
					'order' => array($order)
				);
				print(queryUrl("https://qyapi.weixin.qq.com/cgi-bin/user/update?access_token=$app_access_token", $header, json_encode($msg2)));
				echo "\n";
			}
		}
```

bmh_db 格式: 部门号 部门名 上级部门号 排序号 员工1 员工2 ...
```
	100 事业部1 1 2000000
	10001 业务组1 100 1999000 1234 1235
	200 事业部2 1 1000000
	20001 业务组2 200 999000 2345 2346 2347
```
