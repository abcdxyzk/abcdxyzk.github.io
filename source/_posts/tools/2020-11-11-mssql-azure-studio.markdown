---
layout: post
title: "SQL Server 图形化软件 Azure Data Studio"
date: 2020-11-11 18:34:00 +0800
comments: false
categories:
- 2020
- 2020~11
- tools
- tools~sqlservel
tags:
---


#### 安装libXScrnSaver

```
	yum install libXScrnSaver
```

#### azuredatastudio 1.23

https://docs.microsoft.com/zh-cn/sql/azure-data-studio/download-azure-data-studio?view=sql-server-ver15

```
	wget https://sqlopsbuilds.azureedge.net/stable/d296b6397e0acfddc57e9085e736e084969cdaeb/azuredatastudio-linux-1.23.0.rpm
	rpm -ivh azuredatastudio-linux-1.23.0.rpm

	azuredatastudio
```
#### azuredatastudio 1.13

https://blog.csdn.net/qq_38179971/article/details/102996923

```
	wget https://azuredatastudiobuilds.blob.core.windows.net/releases/1.13.0/azuredatastudio-linux-1.13.0.tar.gz
 
	tar xf azuredatastudio-linux-1.13.0.tar.gz
	cd azuredatastudio-linux-x64
	./azuredatastudio
```


