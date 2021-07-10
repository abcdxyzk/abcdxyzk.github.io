---
layout: post
title: "PHP_XLSXWriter 导出excel"
date: 2021-07-10 10:21:00 +0800
comments: false
categories:
- 2021
- 2021~07
- language
- language~php
tags:
---

[PHP_XLSXWriter.tar](/download/tools/PHP_XLSXWriter.tar)

http://www.lynnk.cn/post/49.html

https://github.com/mk-j/PHP_XLSXWriter

https://blog.csdn.net/qq_41049126/article/details/89532403

https://learnku.com/articles/43135

相比于PHPExcel，PHP_XLSXWriter是一个小而强悍的Excel读写插件，它并没有PHPExcel功能丰富，但是它导出速度非常快，非常适合于数据量特别大，报表格式不是很复杂的导出需求

```
	<?php
	include_once("xlsxwriter.class.php");
	ini_set('display_errors', 0);
	ini_set('log_errors', 1);
	error_reporting(E_ALL & ~E_NOTICE);
	 
	//设置 header，用于浏览器下载
	$filename = "example.xlsx";
	header('Content-disposition: attachment; filename="' . XLSXWriter::sanitize_filename($filename) . '"');
	header("Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet");
	header('Content-Transfer-Encoding: binary');
	header('Cache-Control: must-revalidate');
	header('Pragma: public');
	 
	# 表格样式
	$styles1 = array(
	    'font'         => 'Arial',
	    'font-size'    => 12,
	    'font-style'   => 'bold',  #bold, italic, underline, strikethrough or multiple ie: 'bold,italic'
	    'color'        => '#333',
	    'fill'         => '#fff',  # 背景填充
	    'halign'       => 'center',  # 水平位置 general, left, right, justify, center
	    'border'       => 'left,right,top,bottom', # 边界 left, right, top, bottom, or multiple ie: 'top,left'
	    'border-style' => 'thin',  # 边框样式 thin, medium, thick, dashDot, dashDotDot, dashed, dotted, double, hair, mediumDashDot, mediumDashDotDot, mediumDashed, slantDashDot
	    'border-color' => '#333',  # 边框颜色 #RRGGBB, ie: #ff99cc or #f9c
	    'valign'       => 'center', # 垂直位置 bottom, center, distributed
	    'height'       => 50,  # 行高
	    // 'collapsed'       => true,  # 未知
	    // 'hidden'       => true,  # 隐藏行
	);
	 
	# 每列标题头
	$header = array(
	    'created'     => 'date',
	    'product_id'  => 'integer',
	    'quantity'    => '#,##0.00', #价格 #,##0.00表示小数位两个，减少或增加改变长度
	    'amount'      => 'price',
	    'description' => 'string',
	    'tax'         => '[$$-1009]#,##0.00;[RED]-[$$-1009]#,##0.00',
	);
	 
	# 列样式
	$col_options = [
	    'widths'       => [20, 30, 20, 40, 40],  # 宽度
	    'auto_filter'  => true,  # 筛选
	    // 'freeze_rows'=>true,  # 冻结
	    // 'freeze_columns'=>true,  # 冻结
	    // 'suppress_row' => true,
	];
	 
	# 表数据
	$rows = array(
	    array('2015-01-01', '1', '-50.5', '2010-01-01 23:00:00', '2012-12-31 23:00:00', '=D2'),
	    array('2003', '=B1', '23.5', '2010-01-01 00:00:00', '2012-12-31 00:00:00', '=D2*0.05'),
	);
	 
	$writer = new XLSXWriter();
	 
	$writer->setTitle('标题');
	$writer->setSubject('主题');
	$writer->setAuthor('作者名字');
	$writer->setCompany('公司名字');
	$writer->setKeywords('关键字');
	$writer->setDescription('描述');
	$writer->setTempDir('临时目录');
	# 合并单元格，第一行的大标题
	$writer->markMergedCell('Sheet1', $start_row = 0, $start_col = 0, $end_row = 0, $end_col = 5);
	# 每列标题头
	$writer->writeSheetHeader('Sheet1', $header, $col_options);
	 
	# 表数据行插入
	foreach ($rows as $row) {
	    $writer->writeSheetRow('Sheet1', $row, $styles1);
	}
	 
	#统计行数 返回行数
	$writer->countSheetRows('Sheet1');
	 
	# 输出文档
	$writer->writeToStdOut();
	// $writer->writeToFile('example.xlsx');
	// echo $writer->writeToString();  #没什么卵用
	// $writer->log('错误信息');  # 控制台输出错误信息  数据支持数组、字符串
	exit(0);
```

```
	代码实现:
	//writer 类
	$writer = new XLSXWriter();
	// 文件名
	$filename = "example.xlsx";
	// 设置 header，用于浏览器下载
	header('Content-disposition: attachment; filename="'.XLSXWriter::sanitize_filename($filename).'"');
	header("Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet");
	header('Content-Transfer-Encoding: binary');
	header('Cache-Control: must-revalidate');
	header('Pragma: public');
	
	// 导出的数据
	$string = array (
	0 =>
	array (
	'payc_bill_time' => '2017-07-12 16:40:44',
	'payt_received_date' => '2017-07-12',
	'ci_name' => ' 租金 ',
	'payt_num' => 'YRZB(2012)A0047',
	'payt_scsr_name' => ' 李巧红 ',
	'payt_received' => '300.00',
	'paytd_type' => ' 现金 ',
	'emp_name' => ' 郑振标 ',
	),
	1 =>
	array (
	'payc_bill_time' => '2017-07-12 16:39:55',
	'payt_received_date' => '2017-07-12',
	'ci_name' => ' 租金 ',
	'payt_num' => 'YRZB(2012)A0046',
	'payt_scsr_name' => '22222',
	'payt_received' => '45.00',
	'paytd_type' => ' 现金 ',
	'emp_name' => ' 郑振标 ',
	)
	);
	// 每列的标题头
	$title = array (
	0 => ' 开单时间 ',
	1 => ' 收款时间 ',
	2 => ' 开票项目 ',
	3 => ' 票据编号 ',
	4 => ' 客户名称 ',
	5 => ' 实收金额 ',
	6 => ' 收款方式 ',
	7 => ' 收款人 ',
	);
	// 工作簿名称
	$sheet1 = 'sheet1';
	
	// 对每列指定数据类型，对应单元格的数据类型
	foreach ($title as $key => $item){
	$col_style[] = $key ==5 ? 'price': 'string';
	}
	
	// 设置列格式，suppress_row: 去掉会多出一行数据；widths: 指定每列宽度
	$writer->writeSheetHeader($sheet1, $col_style, ['suppress_row'=>true,'widths'=>[20,20,20,20,20,20,20,20]] );
	// 写入第二行的数据，顺便指定样式
	$writer->writeSheetRow ($sheet1, ['xxx 财务报表 '],
	['height'=>32,'font-size'=>20,'font-style'=>'bold','halign'=>'center','valign'=>'center']);
	
	/ 设置标题头，指定样式 /
	$styles1 = array ('font'=>' 宋体 ','font-size'=>10,'font-style'=>'bold', 'fill'=>'#eee',
	'halign'=>'center', 'border'=>'left,right,top,bottom');
	$writer->writeSheetRow($sheet1, $title,$styles1);
	// 最后是数据，foreach 写入
	foreach ($data as $value) {
	foreach ($value as $item) { $temp[] = $item;}
	$rows[] = $temp;
	unset($temp);
	}
	$styles2 = ['height'=>16];
	foreach($rows as $row){
	$writer->writeSheetRow($sheet1, $row,$styles2);
	}
	
	// 合并单元格，第一行的大标题需要合并单元格
	$writer->markMergedCell($sheet1, $start_row=0, $start_col=0, $end_row=0, $end_col=7);
	// 输出文档
	$writer->writeToStdOut();
	exit(0);
```

```
	require_once 'tools/PHP_XLSXWriter/xlsxwriter.class.php';

	public function export_xls($dir, $name, $data, $fields, $coms = null, $dstyle = null)
	{
		$writer = new \XLSXWriter();
		$sheet1 = 'sheet1';

		$n = 0;
		$i = 0;
		$col_style = array();
		foreach ($fields as $field) {
			$title = isset($coms[$i]) ? $coms[$i] : ((in_array('bm', $fields) and $field == 'zgxz') ? 'lb' : $field);
			if (in_array($field, ['gwjb', 'xj']))
				$col_style[$title] = 'string';
			else
				$col_style[$title] = 'string';
			$i ++;
		}
		$writer->writeSheetHeader($sheet1, $col_style); //, ['suppress_row' => true]);

		$data_style = array();
		foreach ($data as $row) {
			$tmp = array();
			foreach ($fields as $field)
				if (isset($row[$field]))
					$tmp[] = $row[$field];
				elseif (in_array($field, ['gwjb', 'xj']))
					$tmp[] = '0';
				else
					$tmp[] = '';
			$writer->writeSheetRow($sheet1, $tmp, $data_style);
			$n += 1;
		}

		$path = "/var/www/html/downloads/{$dir}/";
		if (!is_dir($path))
			mkdir($path);
		$filename = "{$path}/{$name}.xlsx";
		$writer->writeToFile($filename);
		return ['filename' => $filename, 'row' => $n, 'col' => count($fields), 'size' => filesize($filename)];
	}

```
