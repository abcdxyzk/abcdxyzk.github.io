---
layout: post
title: "js异步、同步ajax"
date: 2021-05-31 13:53:00 +0800
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
	<input type='button' id='modify_{$table}' onClick="noModify('{$table}', 'modify_{$table}')" value='test'>

```

#### js
```
	function noModify(table, bid)
	{
		var idadd = 'is_add_' + table;
		var param = {};
		param[idadd] = '1';
		htmlobj=$.ajax({
			type: 'post',
			url: test.php,
			async: true,
			data: param,
			success: function(html) {
				document.getElementById(bid).disabled = 'disabled';
			}
		});
	}
```

