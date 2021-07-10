---
layout: post
title: "PHPExcel 读写"
date: 2021-07-10 09:38:00 +0800
comments: false
categories:
- 2021
- 2021~07
- language
- language~php
tags:
---

[PHPExcel.tar](/download/tools/PHPExcel.tar)

https://www.cnblogs.com/cyfblogs/p/10115541.html

https://blog.csdn.net/beyond__devil/article/details/53457849

http://www.thinkphp.cn/code/2143.html

https://blog.csdn.net/nizaiwoan/article/details/88635315


## 读
```
	require_once  'tools/PHPExcel/Classes/PHPExcel.php';
	require_once  'tools/PHPExcel/Classes/PHPExcel/IOFactory.php';


	public function readExcelOne($filename, $fullname, $fullpath, $type, $kqny)
	{
		$ret = 0;
		if (!file_exists($fullpath)) {
			die('no file!');
		}

		$readfile = $fullpath;

		if ($type == 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet') {
			$objReader = PHPExcel_IOFactory::createReader('Excel2007');
			$objReader->setLoadSheetsOnly('月汇总');
			$objReader->setReadDataOnly(true);
			$objPHPExcel = $objReader->load($fullpath, 'utf-8');
		} elseif ($type == 'application/vnd.ms-excel') {
			$objReader = PHPExcel_IOFactory::createReader('Excel5');
			$objReader->setLoadSheetsOnly('月汇总');
			$objReader->setReadDataOnly(true);
			//$objPHPExcel = $objReader->load($fullpath, 'utf-8');
			$objPHPExcel = $objReader->load($readfile, 'utf-8');
		} elseif ($type == 'text/csv') {
			# TODO
		} else {
			die('文件类型错误');
		}

		$n = 0;
		//while ($n < $objPHPExcel->getSheetCount())
		{
			$sheet = $objPHPExcel->getSheetByName('月汇总');

			$highestRow = $sheet->getHighestRow(); // 取得总行数
			$highestColumn = $sheet->getHighestColumn(); // 取得总列数

			//echo 'sheet: ' . $n . ', 行数 = '. (string)($highestRow-1) . ', 列数 = '. (string)$highestColumn . ' <br>';

			/*
			if ($highestColumn > 'H')
				$highestColumn = 'H';
			if ($highestRow < 2 || $highestColumn != 'H') {
				die('文件至少两行，至少8列');
			}
			*/

			$j = 1;
			$str = '';
			for ($k = 'A'; $k <= $highestColumn; $k++) {
				$str .= '"' . $sheet->getCell("$k$j")->getValue() . '"';//读取单元格
			}
			//echo $str . ' <br /><br />';
			if ($str != '"事件类型""控制器名称""卡号""持卡人""所属部门""事件时间""门禁点名称""事件源"') {
//				die('列的顺序固定为：事件类型 控制器名称 卡号 持卡人 所属部门 事件时间 门禁点名称 事件源');
			}
			$n = $n + 1;
		}

		$n = 0;
//		while ($n < $objPHPExcel->getSheetCount())
		{
			$sheet = $objPHPExcel->getSheetByName('月汇总');
			$highestRow = $sheet->getHighestRow(); // 取得总行数
			$highestColumn = $sheet->getHighestColumn(); // 取得总列数

			$filerow = 0;
			$inbmh = '';
			$empty_r = 0;
			for($j = 5; $j <= $highestRow; $j ++) {
				$kv = ['no' => 1,
					'zgh' => 2,
					'xm' => 3,
					'bz' => 25];
				$args = array();
				foreach ($kv as $k => $v) {
					$vv = chr(ord('A') - 1 + $v);
					try {
						$args[$k] = (string)$sheet->getCell("$vv$j")->getCalculatedValue(); //读取单元格
					} catch (Exception $e) { // 可能链接外部文件
						$args[$k] = (string)$sheet->getCell("$vv$j")->getValue(); //读取单元格
					}
				}
				if (strlen($args['zgh']) < 4 or !is_numeric($args['zgh'])) {
					$empty_r ++;
					if ($empty_r > 100)
						break;
					continue;
				}
				$empty_r = 0;

				$filerow++;
			}
			$n = $n + 1;
		}
		return $ret;
	}
```

## 写

```
	public function export_xls2($dir, $name, $data, $fields, $coms = null, $colstyle = null, $sheetname = null)
	{
		// 文件名和文件类型
		$path = "/var/www/html/downloads/{$dir}/";
		if (!is_dir($path))
			mkdir($path);
		$filename = "{$path}/{$name}.xlsx";
		$filetype = "xlsx";

		$obj = new PHPExcel();

		// 以下内容是excel文件的信息描述信息
		$obj->getProperties()->setCreator(''); //设置创建者
		$obj->getProperties()->setLastModifiedBy(''); //设置修改者
		$obj->getProperties()->setTitle(''); //设置标题
		$obj->getProperties()->setSubject(''); //设置主题
		$obj->getProperties()->setDescription(''); //设置描述
		$obj->getProperties()->setKeywords('');//设置关键词
		$obj->getProperties()->setCategory('');//设置类型

		// 设置当前sheet
		$obj->setActiveSheetIndex(0);

		$sheet = $obj->getActiveSheet();

		// 设置当前sheet的名称
		if ($sheetname != null)
			$sheet->setTitle($sheetname);

		// 列标
		$list = array();
		for ($i = 0; $i < count($fields); $i ++) {
			$A = (int)($i / 26);
			$B = (int)($i % 26);
			$L = ($A > 0) ?  chr($A+64).chr($B+65) : chr($B+65);
			array_push($list, $L);

			$field = $fields[$i];
			$title = isset($coms[$i]) ? $coms[$i] : ((in_array('bm', $fields) and $field == 'zgxz') ? 'lb' : $field);
			// 填充第一行数据
			$sheet->setCellValue($L . '1', $title);

			if ($colstyle == null)
				$sheet->getStyle($L)->getNumberFormat()->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_TEXT);
			else {
			//	if ($colstyle[$i] == '0')
			//		$sheet->getStyle($L)->getNumberFormat()->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_NUMBER);
				if ($colstyle[$i] == '1')
					$sheet->getStyle($L)->getNumberFormat()->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_TEXT);
			}
		}
/*
		// 设置列宽
		$sheet->getColumnDimension('A')->setWidth(40);
		$sheet->getColumnDimension('B')->setWidth(10);
*/

		$n = 1;
		foreach ($data as $row) {
			$n = $n + 1;
			for ($i = 0; $i < count($fields); $i ++) {
				$field = $fields[$i];
				if ($colstyle == null)
					$sheet->setCellValueExplicit($list[$i] . ($n), $row[$field]); // PHPExcel_Cell_DataType::TYPE_STRING);
				else {
					if ($colstyle[$i] == '0')
						$sheet->setCellValue($list[$i] . ($n), $row[$field]); //, PHPExcel_Cell_DataType::TYPE_NUMERIC);
					if ($colstyle[$i] == '1')
						$sheet->setCellValueExplicit($list[$i] . ($n), $row[$field], PHPExcel_Cell_DataType::TYPE_STRING);
				}
			}
		}

		// 导出
		ob_clean();
		if ($filetype == 'xls') {
			/*
			header('Content-Type: application/vnd.ms-excel');
			header('Content-Disposition: attachment;filename="' . $filename);
			header('Cache-Control: max-age=1');
			$objWriter = new PHPExcel_Writer_Excel5($obj);
			$objWriter->save('php://output');
			*/
			$objWriter = new PHPExcel_Writer_Excel5($obj);
			$objWriter->save($filename);
		} elseif ($filetype == 'xlsx') {
			/*
			header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
			header('Content-Disposition: attachment;filename="' . $filename);
			header('Cache-Control: max-age=1');
			$objWriter = PHPExcel_IOFactory::createWriter($obj, 'Excel2007');
			$objWriter->save('php://output');
			*/
			$objWriter = PHPExcel_IOFactory::createWriter($obj, 'Excel2007');
			$objWriter->save($filename);
		}
		return ['filename' => $filename, 'row' => $n-1, 'col' => count($fields), 'size' => filesize($filename)];
	}
```
