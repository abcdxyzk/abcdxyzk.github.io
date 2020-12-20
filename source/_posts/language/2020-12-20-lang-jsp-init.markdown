---
layout: post
title: "jsp 环境初始化"
date: 2020-12-20 17:49:00 +0800
comments: false
categories:
- 2020
- 2020~12
- language
- language~jsp
tags:
---

## jdk

https://www.oracle.com/java/technologies/javase-jdk15-downloads.html

#### linux 环境变量
```
	vim ~/.bashrc
	export JAVA_HOME=/usr/lib/jvm/jdk-15.0.1/
	export PATH=$PATH:/usr/lib/jvm/jdk-15.0.1/bin
```

#### win 环境变量
右键点击我的电脑，选择属性。点击左边高级系统设置,在弹出的窗口中切换至高级，点击最下面有一个环境变量，进入环境变量设置。

此处需要配置三个系统环境变量：JAVA_HOME 、CLASSPATH、Path;

首先配置点击JAVA_HOME，点击系统变量下的新建，变量名为JAVA_HOME，变量值为安装的jdk所在的路径，我的为：c:\java\jdk-15.0.1，点击确定

然后配置CLASSPATH，与上一步相同，点击新建，变量名为CLASSPATH，变量值为 .;%JAVA_HOME%\lib\dt.jar;%JAVA_HOME%\lib\tools.jar;  ，注意最前面有个点不能省去，最后面要有英文的分号，点击确定

最后配置Path，在系统变量中找到Path并双击它，在变量值的最后加上：%JAVA_HOME%\bin;%JAVA_HOME%\jre\bin;最后点击确定

## mysql

#### win

https://dl.pconline.com.cn/download/2260891.html

选择 server only

系统可能需要依赖 .NET Framework 4.5.2 https://www.microsoft.com/en-us/download/confirmation.aspx?id=42642


#### mysql如何修改root用户的密码
```
	格式：mysql> set password for 用户名@localhost = password('新密码'); 
	例子：mysql> set password for root@localhost = password('123'); 
```

#### 增加内容
```
	create database test;
	use test
	create table info(id integer primary key, name varchar(30), val integer);
	alter table info modify id integer auto_increment;
	insert into info(name, val) values ('kk', 123);
	insert into info(name, val) values ('ll', 456);
	select * from info;
	quit
```

## tomcat

https://tomcat.apache.org/download-90.cgi

将 mysql-connector-java-5.1.47.jar 放到 lib/ 下

vim webapps/examples/db.jsp
```
	<%@ page language="java" contentType="text/html; charset=UTF-8" pageEncoding="UTF-8"%>
	<%@ page import="java.sql.*"%>
	
	<html>
	<head>
	<title>通过JSP打开数据表</title>
	</head>
	<body>
	<%
		try {
			Class.forName("com.mysql.jdbc.Driver");  //驱动程序名
			String url = "jdbc:mysql://localhost:3306/test"; //数据库名
			String username = "root";  //数据库用户名
			String password = "123";  //数据库用户密码
			Connection conn = DriverManager.getConnection(url, username, password);  //连接状态
	
			if (conn != null) {
				out.print("数据库连接成功！");
				out.print("<br />");
				Statement stmt = null;
				ResultSet rs = null;
				String sql = "SELECT * FROM info;";  //查询语句
				stmt = conn.createStatement();
				rs = stmt.executeQuery(sql);
				out.print("查询结果：");
				out.print("<br />");
				out.println("姓名"+"  "+"性别 "+"  "+"年龄");
				out.print("<br />");
				while (rs.next()) {
					out.println(rs.getString("id")+"   &nbsp  "+rs.getString("name")+"  &nbsp "+rs.getInt("val")); //将查询结果输出
					out.print("<br />");
				}
			} else {
				out.print("连接失败！");
			}
		} catch (Exception e) {
			out.print("数据库连接异常！");
		}
	%>
	</body>
	</html>
```

直接运行 bin/startup.sh

打开 127.0.0.1:8080/examples/db.jsp 即可看到数据库内容

#### log 乱码

打开Tomcat安装源码conf文件夹下的logging.properties文件，将UTF-8改成GBK
