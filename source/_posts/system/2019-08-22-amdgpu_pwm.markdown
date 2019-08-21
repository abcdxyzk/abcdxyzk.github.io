---
layout: post
title: "GPU温控"
date: 2019-08-21 16:53:00 +0800
comments: false
categories:
- 2019
- 2019~08
- system
- system~ubuntu
tags:
---

目录是 /sys/class/drm/card0/device/hwmon/hwmonX/

换内核之类的操作会改变 hwmonX

#### 调节脚本
```
	#!/usr/bin/python
	
	import commands;
	import time;
	
	t0=0
	temp_inc=[90000, 85000, 80000, 70000, 60000, 50000, 40000, 00000];
	pwm_inc=[ 245,   205,   165,   125,   105,   85,    65,    45];
	
	temp_dec=[89000, 84000, 79000, 67000, 57000, 47000, 37000, 00000];
	pwm_dec=[ 245,   205,   165,   125,   105,   85,    65,    45];
	
	
	global pwm1
	pwm1=0;
	
	def set_pwm(newpwm):
		global pwm1
		if newpwm != pwm1:
			cmd="echo "+str(newpwm)+" > /sys/class/drm/card0/device/hwmon/hwmon3/pwm1";
			r,o = commands.getstatusoutput(cmd);
			pwm1=newpwm;
	
			#cmd1="cat /sys/class/drm/card0/device/hwmon/hwmon3/pwm1";
			#r,o = commands.getstatusoutput(cmd1);
	                #print cmd
	                #print r, o
	
	r,o = commands.getstatusoutput("echo 1 > /sys/class/drm/card0/device/hwmon/hwmon3/pwm1_enable");
	while 1:
		r,t = commands.getstatusoutput("cat /sys/class/drm/card0/device/hwmon/hwmon3/temp1_input");
		t = int(t);
		if t - t0 > 0:
			for i in range(0, 8):
				if t >= temp_inc[i]:
					break;
			#print "inc ", t, temp_inc[i], pwm_inc[i]
			set_pwm(pwm_inc[i]);
		elif t - t0 < 0:
			for i in range(0, 8):
				if t >= temp_dec[i]:
					break;
			#print "dec ", t, temp_dec[i], pwm_dec[i]
			set_pwm(pwm_dec[i]);
	
		t0 = t;
		time.sleep(10);
```

