---
layout: post
title: "table行随鼠标移动变色示例"
date: 2021-05-29 14:16:00 +0800
comments: false
categories:
- 2021
- 2021~05
- language
- language~web
tags:
---

https://www.jiangweishan.com/article/svg1486310400790.html

#### 1、设计表格
```
	<body class="html_body">
	<div class="body_div">
	<table id="tab">
		<tr style="background: #000000;color: #FFFFFF;font-weight: bolder;">
			<th>工号</th>
			<th>姓名</th>
			<th>年龄</th>
			<th>性别</th>
		</tr>
		<tr>
			<td>2014010101</td>
			<td>张峰</td>
			<td>56</td>
			<td>男</td>
		</tr>
		<tr>
			<td>2014010102</td>
			<td>李玉</td>
			<td>42</td>
			<td>女</td>
		</tr>
		<tr>
			<td>2014010103</td>
			<td>王珂</td>
			<td>36</td>
			<td>男</td>
		</tr>
		<tr>
			<td>2014010104</td>
			<td>张钰</td>
			<td>31</td>
			<td>女</td>
		</tr>
		<tr>
			<td>2014010105</td>
			<td>朱顾</td>
			<td>44</td>
			<td>男</td>
		</tr>
		<tr>
			<td>2014010106</td>
			<td>胡雨</td>
			<td>35</td>
			<td>女</td>
		</tr>
		<tr>
			<td>2014010107</td>
			<td>刘希</td>
			<td>30</td>
			<td>男</td>
		</tr>
		<tr>
			<td>2014010108</td>
			<td>孙宇</td>
			<td>45</td>
			<td>女</td>
		</tr>
		<tr>
			<td>2014010109</td>
			<td>谷雨</td>
			<td>33</td>
			<td>男</td>
		</tr>
		<tr>
			<td>2014010110</td>
			<td>科宇</td>
			<td>45</td>
			<td>女</td>
		</tr>
	</table>
	</div>
	</body>
```

#### 2、设计样式
```
	.html_body .body_div {
		width: 1340;
		height: 595;
	}
	.body_div {
		font-size: 12px;
		background-color: #CCCCCC;
	}
	.tr_odd {
		background-color: orange;
	}
	.tr_even {
		background-color: aqua;
	}
	.mouse_color {
		background-color: green;
	}
	#tab {
		border: 1px #FF0000 solid;
		text-align: center;
		width: 100%;
		height: 100%;
	}
```

#### 3、设计JS
```
	//设置奇数行背景色
	//$("#tab tr:odd").find("td").addClass("tr_odd");
	$("#tab tr:odd").addClass("tr_odd");
	//设置偶数行背景色
	$("#tab tr:even").addClass("tr_even");

	// 鼠标移到的颜色
	$("#tab tr").mouseover(function() {
		$(this).addClass("mouse_color");
	});

	// 鼠标移出的颜色
	$("#tab tr").mouseout(function() {
		$(this).removeClass("mouse_color");
	});
```
