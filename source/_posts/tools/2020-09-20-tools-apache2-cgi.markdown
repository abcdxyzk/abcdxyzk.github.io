---
layout: post
title: "apache2 支持cgi"
date: 2020-09-20 14:20:00 +0800
comments: false
categories:
- 2020
- 2020~09
- tools
- tools~apache2
tags:
---

http://blog.chinaunix.net/uid-26824563-id-5769678.html

#### ln

```
	ls -l /etc/apache2/*/*cgi*

	ln -s /etc/apache2/mods-available/cgid.conf /etc/apache2/mods-enabled/cgid.conf
	ln -s /etc/apache2/mods-available/cgid.load /etc/apache2/mods-enabled/cgid.load
	ln -s /etc/apache2/mods-available/cgi.load /etc/apache2/mods-enabled/cgi.load 
```

#### restart

service apache2 restart

CGI目录为 /usr/lib/cgi-bin
