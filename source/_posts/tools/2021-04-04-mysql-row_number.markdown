---
layout: post
title: "mysql 分组后的组内排序"
date: 2021-04-04 21:34:00 +0800
comments: false
categories:
- 2021
- 2021~04
- tools
- tools~mysql
tags:
---

## 方法一

https://www.cnblogs.com/hxfcodelife/p/10226934.html

数据库的查询顺序是先分组的，最后才将结果进行排序。 group by XX order by yy 是不行的

#### 用 row_number() over(partition by XX order by YY desc)
```
	select a.Classid,a.English from (select Classid,English,row_number() over(partition by Classid order by English desc) as n from CJ) a where n<=2
```


## 方法二

https://blog.csdn.net/u014508939/article/details/100561133

#### 用 GROUP_CONCAT, SUBSTRING_INDEX

```
	SELECT SUBSTRING_INDEX(GROUP_CONCAT(id, ORDER BY update_time DESC), ',', 1) FROM goods GROUP BY good_id OEDER by update_time DESC;
```

