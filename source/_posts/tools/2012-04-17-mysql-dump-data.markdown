---
layout: post
title: "mysql 导入、导出"
date: 2012-04-17 18:40:00 +0800
comments: false
categories:
- 2012
- 2012~05
- tools
- tools~mysql
tags:
---

#### source 导入
mysql>use 数据库  
然后用source，后面参数为脚本文件（如这里用到的.sql）
```
	mysql>source /home/abcdxyzk/chai.sql
```

#### 命令导入

```
	mysql -upx -ppx px < pxbak.sql
```

#### 导出表结构和数据

配合git做数据库备份, 加 --skip-extended-insert 不要合并插入数据。
```
	mysqldump --skip-extended-insert -h localhost -uroot -p123456 database table > dump.sql
```

导出单个数据表结构（包含数据）
```
	mysqldump -h localhost -uroot -p123456 -d database > dump.sql

	mysqldump -h localhost -uroot -p123456 -d database table > dump.sql
```

导出整个数据库结构（不包含数据）
```
	mysqldump -d -h localhost -uroot -p123456 database table > dump.sql
```

