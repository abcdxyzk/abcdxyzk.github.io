---
layout: post
title: "idea Spring MVC"
date: 2020-12-20 18:30:00 +0800
comments: false
categories:
- 2020
- 2020~12
- language
- language~jsp
tags:
---

https://www.cnblogs.com/wormday/p/8435617.html

#### 创建Spring MVC项目

File -> new -> Project... -> Spring -> Spring MVC

#### 配置

run -> Edit Configurations... 

![](/images/lang/2020-12-20-1.png)

![](/images/lang/2020-12-20-2.png)

![](/images/lang/2020-12-20-3.png)


#### 配置

File -> Project Structure...

![](/images/lang/2020-12-20-4.png)


![](/images/lang/2020-12-20-5.png)

#### 运行

浏览器输入地址 http://localhost:8080/index.jsp


## 添加Controller

站点可以打开了，不过我们这个不是MVC，因为没有M、没有V也没有C

我们就从MVC中的C（Controller）开始，继续配置

在新建Controller之前，首先要建一个包，SpringMVC是没法运行在默认包下的，按照如下方式建包，

我建的包名称为：test

其实包名随意，但是必须要有。。。

再这个包下新建Java Class文件 MyController

```
	package test;

	import org.springframework.ui.Model;
	import org.springframework.stereotype.Controller;
	import org.springframework.web.bind.annotation.RequestMapping;
	import test.dao.BaseDao;

	import java.sql.*;

	@Controller
	@RequestMapping("/mvc")
	public class MyController {

		static BaseDao baseDB = new BaseDao();

		@RequestMapping("/hello")
		public String hello(Model model) {
			model.addAttribute("name","nameval");
			return "hello";
		}

		@RequestMapping("/db")
		public String db(Model model) {

			String ou = "";

			try {
				ResultSet rs  = baseDB.executeQuery("SELECT * FROM info;");
				while (rs.next()) {
					ou = ou + rs.getString("id") + "   &nbsp  " + rs.getString("name") + "  &nbsp " + rs.getInt("val"); //将查询结果输出
				}
			} catch (Exception e) {
				return "err";
			}

			model.addAttribute("dv", ou);
			return "db";
		}
	}
```

也可以只把注解写在方法上，比如@RequestMapping("mvc/hello")

这个Controller的Action 地址应该是：

http://localhost:8080/mvc/hello.form   其实这个时候访问结果是404，因为后边还有不少配置没有做...

## 修改 url-pattern（web.xml）

先打开web\WEB-INF\web.xml文件

有关于ServletMapping的设置，通过这个设置，可以配置那些类型的url用那些servlet来处理

```
	<servlet>
		<servlet-name>dispatcher</servlet-name>
		<servlet-class>org.springframework.web.servlet.DispatcherServlet</servlet-class>
		<load-on-startup>1</load-on-startup>
	</servlet>
	<servlet-mapping>
		<servlet-name>dispatcher</servlet-name>
		<url-pattern>*.form</url-pattern>
	</servlet-mapping>
```

结合这一段xml，我们可以看到,IDEA默认帮我配置了一个名字叫做dispatcher的Servlet

这个Servlet使用org.springframework.web.servlet.DispatcherServlet这个类来处理

这个Servlet对应的Url是`*.form`

如果你跟我一样不喜欢每个MVC Url后边都带一个form，可以改成斜杠

```
	<url-pattern>/</url-pattern>
```

如果你现在重新启动程序，然后继续访问http://localhost:8080/mvc/hello

发现，依旧404，并且伴随每次访问，都在Server的Output窗口有一个错误日志

org.springframework.web.servlet.PageNotFound.noHandlerFound No mapping found for HTTP request with URI [/mvc/hello] in DispatcherServlet with name 'dispatcher'

意思就是没有找到相应的Controller，不但要把Controller的代码写好，还要告诉Spring(在这里其实是dispatcher servlet)去哪里找这些Controller。。。

作为验证，你可以在Controller里边加一个断点，然后刷新页面，程序根本就没有执行到Controller里边

## 配置 component-scan（dispatcher-servlet.xml）

component-scan就是告诉Servlet去哪里找到相应的Controller

打开 dispatcher-servlet.xml

在已经存在的<beans></beans>中间加上

```
	<context:component-scan base-package="test"/>
```

再加上view前后缀
```
	<?xml version="1.0" encoding="UTF-8"?>
<beans xmlns="http://www.springframework.org/schema/beans"
	   xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
	   xmlns:context="http://www.springframework.org/schema/context"
	   xmlns:mvc="http://www.springframework.org/schema/mvc"
	   xsi:schemaLocation="http://www.springframework.org/schema/beans http://www.springframework.org/schema/beans/spring-beans.xsd http://www.springframework.org/schema/context https://www.springframework.org/schema/context/spring-context.xsd http://www.springframework.org/schema/mvc https://www.springframework.org/schema/mvc/spring-mvc.xsd">

	<context:component-scan base-package="test"/>

	<!-- don't handle the static resource -->
	<mvc:default-servlet-handler />

	<!-- if you use annotation you must configure following setting -->
	<mvc:annotation-driven />

	<!-- configure the InternalResourceViewResolver -->
	<bean class="org.springframework.web.servlet.view.InternalResourceViewResolver"
		  id="internalResourceViewResolver">
		<!-- 前缀 -->
		<property name="prefix" value="/WEB-INF/jsp/" />
		<!-- 后缀 -->
		<property name="suffix" value=".jsp" />
	</bean>
</beans>
```
base-package指定的就是存放Controller的包

做完这一步之后，重新启动项目，再次访问  http://localhost:8080/mvc/hello

这次应该还是404错误，不过比刚才的404错误前进了一大步

毕竟这次Controller已经执行了，如果刚才的断点没有去掉，你可以验证一下看看

这一回是因为是“hello”这个View找不到（我们刚才确实只是告诉他这个位置，但是从来没有创建过这个文件）


## 添加视图文件(.jsp)

在 web/WEB-INF/jsp 下创建 hello.jsp

```
	<%@ page contentType="text/html;charset=UTF-8" language="java" %>
	<html>
	<head>
	    <title>Title</title>
	</head>
	<body>
	${name}, Hello world!!!!
	</body>
	</html>
```

## 通过 Model 向 View 传值

通过上面的操作，已经完成了MVC中的(V和C)，M还没见影子，让我们继续修改

打开刚才定义的Controller 也就是 MyController.java文件

增加 ui.Model


#### 打开copy来的项目时

要在 File -> Project Structure... -> Project Settings -> Project -> SDK -> new sdk -> JDK 主路经: /usr/lib/jvm/jdk-15.0.1

#### err1: Unsupported class file major version 57

File -> Settings...

Build, Execution, Deployment -> Compiler -> Java Compiler 修改 project bytecode version

#### err2: java.lang.ClassNotFoundException: org.springframework.web.context.ContextLoaderListener

在IDEA中点击File > Project Structure > Artifacts > 在右侧Output Layout右击项目名，选择Put into Output Root。

执行后，在WEB-INF在增加了lib目录，里面是项目引用的jar包，点击OK。

![](/images/lang/2020-12-20-6.jpeg)


[mvc example](https://github.com/abcdxyzk/idea_springmvc/raw/master/spring_mvc.tar.gz)


