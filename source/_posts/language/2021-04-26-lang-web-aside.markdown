---
layout: post
title: "CSS实现侧边栏导航 + 透明"
date: 2021-04-26 21:10:00 +0800
comments: false
categories:
- 2021
- 2021~04
- language
- language~web
tags:
---

https://www.cnblogs.com/liubingyjui/p/12698504.html

#### css background背景透明
```
	background: transparent;
	background: rgba(0, 0, 0, 0.8);
```

#### CSS实现侧边栏导航

```
	<!DOCTYPE html>
	<html>
		<head>
			<meta charset="UTF-8">
			<title></title>
			<style type="text/css">
			/*隐藏checked复选框*/
				#sidemenu{
					display: none;
				}
	
				#sidemenu:checked + aside {
					/*为被选中的sidemenu后的aside设置属性（紧邻）*/
					left: 0;
					/*点击按钮即选中checked后,侧边栏位置变为贴着左边,配合ease-out使用,有渐变滑出的效果*/
				}
	
				#sidemenu:checked ~ #wrap {
					/*为被选中的sidemenu后的wrap设置属性（非紧邻）*/
					padding-left: 220px;
				}
	
				aside {
					/*侧边栏,初始位置为-200px，即隐藏效果*/
					position: absolute;
					top: 0;
					bottom: 0;
					left: -200px;
					width: 200px;
					background: black;
					transition: 0.2s ease-out;
					/*动画效果的执行方式是ease-out，即侧边栏滑动效果为渐变式，而不是生硬的突然变化*/
				}
	
				h2 {
					color: white;
					text-align: center;
					font-size: 2em;
				}
	
				/*控制侧边栏进出的按钮（外部包裹）*/
				#wrap {
					margin-left: 20px;
					padding: 10px;
					transition: 0.2s ease-out;
				}
	
				/*控制侧边栏进出的按钮（内部文字样式）*/
				label {
					/*控制侧边栏进出的按钮*/
					background: white;
					border-radius: 70px;
					color: orange;
					cursor: pointer;
					display: block;
					font-family: Courier New;
					font-size: 2em;
					width: 1.5em;
					height: 1.5em;
					line-height: 1.5em;
					text-align: center;
					display: inline-block;
				}
	
				label:hover {
					background: #000;
				}
	
				#sideul li {
					list-style: none;
					color: white;
					width: 100%;
					height: 1.8em;
					font-size: 1.5em;
					text-align: center;
				}
	
				a {
					text-decoration: none;
				}
	
				#sideul li:hover {
					color: orange;
				}
			</style>
		</head>
	
		<body>
			<input type='checkbox' id='sidemenu'>
			<aside>
				<h2>主菜单</h2>
				<br />
				<ul id="sideul">
					<a href="##">
						<li>首页</li>
					</a>
					<a href="##">
						<li style="color: orange;">导航1</li>
					</a>
					<a href="##">
						<li>导航2</li>
					</a>
					<a href="##">
						<li>导航3</li>
					</a>
					<a href="##">
						<li>导航4</li>
					</a>
					<a href="##">
						<li>导航5</li>
					</a>
					<a href="##">
						<li>导航6ʳ</li>
					</a>
				</ul>
			</aside>
			<div id='wrap'>
				<label id='sideMenuControl' for='sidemenu'>≡</label>
				<!--for 属性规定 label 与哪个表单元素绑定，即将这个控制侧边栏进出的按钮与checkbox绑定-->
			</div>
		</body>
	</html>
```

