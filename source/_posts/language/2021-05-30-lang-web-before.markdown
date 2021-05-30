---
layout: post
title: "页面内容已修改，您确定要离开？"
date: 2021-05-30 17:34:00 +0800
comments: false
categories:
- 2021
- 2021~05
- language
- language~web
tags:
---

#### html
```
	<input style='display:none' name='beforeunload' id='beforeunload' value='0'>

	<button id='{$idpre}_edit' type='button' class=submit onclick=enable_all()>编辑</button>
	<input id='{$addidpre}_submit' class='submit' type='submit' value='提交' onclick=submit_it()>
```

#### js
```
	function enable_all() {
		var b = document.getElementById('beforeunload');
		if (b !== null)
			b.value = 2;
	}

	function submit_it() {
		var b = document.getElementById('beforeunload');
		if (b !== null)
			b.value = 1;
	}

	window.addEventListener("beforeunload", function(e) {
		var val = document.getElementById('beforeunload').value;
		if (val > 1) {
			var confirmationMessage = "您是否要离开 - 您输入的数据可能不会被保存。";
			(e || window.event).returnValue = confirmationMessage; // Gecko and Trident
			return confirmationMessage; // Gecko and WebKit});
		}
	});
```

