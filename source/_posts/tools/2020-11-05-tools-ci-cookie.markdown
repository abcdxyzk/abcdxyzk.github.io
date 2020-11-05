---
layout: post
title: "CI使用cookie"
date: 2020-11-05 16:37:00 +0800
comments: false
categories:
- 2020
- 2020~11
- tools
- tools~ci
---


#### 第一种设置cookie的方式：采用php原生态的方法设置的cookie的值
```
	setcookie("user_id",$user_info['user_id'],86500);
	setcookie("username",$user_info['username'],86500);
	setcookie("password",$user_info['password'],86500);
	//echo $_COOKIE['username'];
```
  
#### 第二种设置cookie的方式：通过CI框架的input类库设置cookie的值
```
	$this->input->set_cookie("username",$user_info['username'],60);
	$this->input->set_cookie("password",$user_info['password'],60);
	$this->input->set_cookie("user_id",$user_info['user_id'],60);
	//echo $this->input->cookie("password");//适用于控制器
	//echo $this->input->cookie("username");//适用于控制器
	//echo $_COOKIE['username'];//在模型类中可以通过这种方式获取cookie值
	//echo $_COOKIE['password'];//在模型类中可以通过这种方式获取cookie值
```
  
#### 第三种设置cookie的方式：通过CI框架的cookie_helper.php辅助函数库设置cookie的值
```
	set_cookie("username",$user_info['username'],60);
	set_cookie("password",$user_info['password'],60);
	set_cookie("user_id",$user_info['user_id'],60);
	//echo get_cookie("username");
```

#### 删除cookie：通过CI框架的cookie_helper.php辅助函数删除cookie
```
	delete_cookie("username");
	delete_cookie("password");
	delete_cookie("user_id");
	header("location:".site_url("common/login"));
```

