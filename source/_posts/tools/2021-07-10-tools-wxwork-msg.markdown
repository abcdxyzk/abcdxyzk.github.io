---
layout: post
title: "企业微信自建应用发送信息"
date: 2021-07-10 10:49:00 +0800
comments: false
categories:
- 2021
- 2021~07
- tools
- tools~wxwork
tags:
---

https://work.weixin.qq.com/api/doc/90000/90135/90236

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
	
		$msg = array(
			'touser' => '2817', 
			'toparty' => '1', 
			'msgtype' => 'text',
			'agentid' => 1000014,
			'text' => array(
				'content' => '测试'
			)
		);
		// TODO
		$app_access_token = '';
		$header = array('Content-Type: application/json');
		print(queryUrl("https://qyapi.weixin.qq.com/cgi-bin/message/send?access_token=$app_access_token", $header, json_encode($msg)));
```
