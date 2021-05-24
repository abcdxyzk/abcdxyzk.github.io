---
layout: post
title: "form 提交前检查"
date: 2021-05-21 15:05:00 +0800
comments: false
categories:
- 2021
- 2021~05
- language
- language~web
tags:
---

https://www.cnblogs.com/min-yu/p/11187485.html

onsubmit只能表单上使用,提交表单前会触发, onclick是按钮等控件使用, 用来触发点击事件。

在提交表单前，一般都会进行数据验证，可以选择在submit按钮上的onclick中验证,也可以在onsubmit中验证。

但是onclick比onsubmit更早的被触发。

onsubmit处理函数返回false，onclick函数返回false，都不会引起表单提交。

#### onsubmit
```
	<script language="javascript">
		function CheckPost()
		{
			if (addForm.user.value == "")
			{
				alert("请填写用户名！");
				addForm.username.focus();
				return false;
			}
			if (addForm.title.value.length < 5)
			{
				alert("标题不能少于5个字符！");
				addForm.title.focus();
				return false;
			}
			return true;
		}
	</script>

	<form action="test.php" method="post" name="addForm" onsubmit="return CheckPost();">
		<div>用户:<input type="text" size="10" name="user" maxlength="20"/></div>
		<div>标题:<input type="text" name="title" maxlength="50"/></div>
		<div>内容:<textarea name="content" rows="8" cols="30"></textarea></div>
		<div><input type="submit" name="submit" value="发表留言"/></div>
	</form>
```

#### onclick
```
	<script language="javascript">
		function SendForm()
		{
			if(CheckPost())
			{
				document.addForm.submit();
			}
		}

		function CheckPost()
		{
			 if (addForm.user.value == "")
			 {
				 alert("请填写用户名！");
				 addForm.username.focus();
				 return false;
			 }
			 if (addForm.title.value.length < 5)
			 {
				 alert("标题不能少于5个字符！");
				 addForm.title.focus();
				 return false;
			 }
			 return true;
		}
	</script>

	<form action="test.php" method="post" name="addForm">
		<div>用户:<input type="text" size="10" name="user" maxlength="20"/></div>
		<div>标题:<input type="text" name="title" maxlength="50"/></div>
		<div>内容:<textarea name="content" rows="8" cols="30"></textarea></div>
		<div><input type="button" name="submit" value="发表留言" onclick="SendForm();"/></div>
	</form>
```

