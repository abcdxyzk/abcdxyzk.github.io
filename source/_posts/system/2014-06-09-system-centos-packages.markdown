---
layout: post
title: "centos系统各种包下载"
date: 2014-06-09 15:15:00 +0800
comments: false
categories:
- 2014
- 2014~06
- system
- system~centos
tags:
---

#### 0 centos 系統原包
爬取el7所有版本
```
	import re
	import urllib

	def getHtml(url):
		page = urllib.urlopen(url)
		html = page.read()
		return html

	def getHref(html, reg):
		reg = re.compile(reg)
		reslist = re.findall(reg, html)
		return reslist

	URL = "https://buildlogs.centos.org/"
	html = getHtml(URL)
	c7Href = getHref(html, r'href="(c7.+)/"')
	for ver in c7Href:
		if '.a32' in ver or '.a64' in ver or '.p32' in ver or '.i386' in ver:
			continue
		url1 = URL + ver + "/kernel/"
		print url1
		html = getHtml(url1)
		dateHref = getHref(html, r'href="(20............)/"')
		for date in dateHref:
			url2 = url1 + date + "/"
			html = getHtml(url2)
			kernelHref = getHref(html, r'href="(.+el7.x86_64)/"')
			for kver in kernelHref:
				print url2 + kver
```

https://buildlogs.centos.org/c7-dotnet/kernel/  
https://buildlogs.centos.org/c7-epel/kernel/  
https://buildlogs.centos.org/c7-extras.x86_64/kernel/  
https://buildlogs.centos.org/c7-plus.x86_64/kernel/  
https://buildlogs.centos.org/c7-plus/kernel/  
https://buildlogs.centos.org/c7-rt/kernel/  
https://buildlogs.centos.org/c7-updates/kernel/  
https://buildlogs.centos.org/c7.00.02/kernel/  
https://buildlogs.centos.org/c7.00.02/kernel/20140529190808/3.10.0-121.el7.x86_64  
https://buildlogs.centos.org/c7.00.03/kernel/  
https://buildlogs.centos.org/c7.00.03/kernel/20140609184350/3.10.0-121.el7.x86_64  
https://buildlogs.centos.org/c7.00.04/kernel/  
https://buildlogs.centos.org/c7.00.04/kernel/20140612172658/3.10.0-123.el7.x86_64  
https://buildlogs.centos.org/c7.00.04/kernel/20140619231033/3.10.0-123.el7.x86_64  
https://buildlogs.centos.org/c7.01.00/kernel/  
https://buildlogs.centos.org/c7.01.00/kernel/20150306113403/3.10.0-229.el7.x86_64  
https://buildlogs.centos.org/c7.01.u/kernel/  
https://buildlogs.centos.org/c7.01.u/kernel/20150327030147/3.10.0-229.1.2.el7.x86_64  
https://buildlogs.centos.org/c7.01.u/kernel/20150513100324/3.10.0-229.4.2.el7.x86_64  
https://buildlogs.centos.org/c7.01.u/kernel/20150623220331/3.10.0-229.7.2.el7.x86_64  
https://buildlogs.centos.org/c7.01.u/kernel/20150806010338/3.10.0-229.11.1.el7.x86_64  
https://buildlogs.centos.org/c7.01.u/kernel/20150915124206/3.10.0-229.14.1.el7.x86_64  
https://buildlogs.centos.org/c7.01.u/kernel/20150915150313/3.10.0-229.14.1.el7.x86_64  
https://buildlogs.centos.org/c7.01.u/kernel/20151103190728/3.10.0-229.20.1.el7.x86_64  
https://buildlogs.centos.org/c7.1511.00/kernel/  
https://buildlogs.centos.org/c7.1511.00/kernel/20151119220809/3.10.0-327.el7.x86_64  
https://buildlogs.centos.org/c7.1511.exp/kernel/  
https://buildlogs.centos.org/c7.1511.exp/kernel/20151016161452/4.2.0-1.centos.el7.x86_64  
https://buildlogs.centos.org/c7.1511.exp/kernel/20151016163253/4.2.0-1.centos.el7.x86_64  
https://buildlogs.centos.org/c7.1511.exp/kernel/20151016164628/4.2.0-1.centos.el7.x86_64  
https://buildlogs.centos.org/c7.1511.exp/kernel/20160321183722/4.3.3-200.el7.x86_64  
https://buildlogs.centos.org/c7.1511.exp/kernel/20160324145107/4.4.6-301.el7.x86_64  
https://buildlogs.centos.org/c7.1511.exp/kernel/20160324192831/4.4.6-301.el7.x86_64  
https://buildlogs.centos.org/c7.1511.exp/kernel/20160325232209/4.4.6-301.el7.x86_64  
https://buildlogs.centos.org/c7.1511.exp/kernel/20160415133359/4.4.7-301.el7.x86_64  
https://buildlogs.centos.org/c7.1511.exp/kernel/20160506113850/4.4.9-301.el7.x86_64  
https://buildlogs.centos.org/c7.1511.exp/kernel/20160601130532/4.4.11-301.el7.x86_64  
https://buildlogs.centos.org/c7.1511.exp/kernel/20160602142804/4.4.12-301.el7.x86_64  
https://buildlogs.centos.org/c7.1511.exp/kernel/20160608070903/4.4.13-301.el7.x86_64  
https://buildlogs.centos.org/c7.1511.exp/kernel/20160620154312/4.4.13-303.el7.x86_64  
https://buildlogs.centos.org/c7.1511.exp/kernel/20160625132228/4.4.14-201.el7.x86_64  
https://buildlogs.centos.org/c7.1511.exp/kernel/20160625133615/4.4.14-201.el7.x86_64  
https://buildlogs.centos.org/c7.1511.exp/kernel/20160815150500/4.4.17-201.el7.x86_64  
https://buildlogs.centos.org/c7.1511.exp/kernel/20160815161333/4.4.17-201.el7.x86_64  
https://buildlogs.centos.org/c7.1511.exp/kernel/20160817141019/4.4.18-201.el7.x86_64  
https://buildlogs.centos.org/c7.1511.u/kernel/  
https://buildlogs.centos.org/c7.1511.u/kernel/20151209124337/3.10.0-327.3.1.el7.x86_64  
https://buildlogs.centos.org/c7.1511.u/kernel/20151209140627/3.10.0-327.3.1.el7.x86_64  
https://buildlogs.centos.org/c7.1511.u/kernel/20160105150501/3.10.0-327.4.4.el7.x86_64  
https://buildlogs.centos.org/c7.1511.u/kernel/20160125220424/3.10.0-327.4.5.el7.x86_64  
https://buildlogs.centos.org/c7.1511.u/kernel/20160217024115/3.10.0-327.10.1.el7.x86_64  
https://buildlogs.centos.org/c7.1511.u/kernel/20160331160950/3.10.0-327.13.1.el7.x86_64  
https://buildlogs.centos.org/c7.1511.u/kernel/20160512110105/3.10.0-327.18.2.el7.x86_64  
https://buildlogs.centos.org/c7.1511.u/kernel/20160623161521/3.10.0-327.22.2.el7.x86_64  
https://buildlogs.centos.org/c7.1511.u/kernel/20160802204906/3.10.0-327.28.2.el7.x86_64  
https://buildlogs.centos.org/c7.1511.u/kernel/20160818163946/3.10.0-327.28.3.el7.x86_64  
https://buildlogs.centos.org/c7.1511.u/kernel/20160918123639/3.10.0-327.36.1.el7.x86_64  
https://buildlogs.centos.org/c7.1511.u/kernel/20161010214658/3.10.0-327.36.2.el7.x86_64  
https://buildlogs.centos.org/c7.1511.u/kernel/20161010215511/3.10.0-327.36.2.el7.x86_64  
https://buildlogs.centos.org/c7.1511.u/kernel/20161024152721/3.10.0-327.36.3.el7.x86_64  
https://buildlogs.centos.org/c7.1611.00/kernel/  
https://buildlogs.centos.org/c7.1611.01/kernel/  
https://buildlogs.centos.org/c7.1611.01/kernel/20161117160457/3.10.0-514.el7.x86_64  
https://buildlogs.centos.org/c7.1611.exp/kernel/  
https://buildlogs.centos.org/c7.1611.exp/kernel/20171018140113/4.9.57-204.el7.x86_64  
https://buildlogs.centos.org/c7.1611.exp/kernel/20171120151900/4.9.63-204.el7.x86_64  
https://buildlogs.centos.org/c7.1611.u/kernel/  
https://buildlogs.centos.org/c7.1611.u/kernel/20161207134106/3.10.0-514.2.2.el7.x86_64  
https://buildlogs.centos.org/c7.1611.u/kernel/20170118010633/3.10.0-514.6.1.el7.x86_64  
https://buildlogs.centos.org/c7.1611.u/kernel/20170223034721/3.10.0-514.2.2.el7.x86_64  
https://buildlogs.centos.org/c7.1611.u/kernel/20170303004149/3.10.0-514.10.2.el7.x86_64  
https://buildlogs.centos.org/c7.1611.u/kernel/20170412150118/3.10.0-514.16.1.el7.x86_64  
https://buildlogs.centos.org/c7.1611.u/kernel/20170525170145/3.10.0-514.21.1.el7.x86_64  
https://buildlogs.centos.org/c7.1611.u/kernel/20170620122143/3.10.0-514.21.2.el7.x86_64  
https://buildlogs.centos.org/c7.1611.u/kernel/20170620132051/3.10.0-514.21.2.el7.x86_64  
https://buildlogs.centos.org/c7.1611.u/kernel/20170628200657/3.10.0-514.26.1.el7.x86_64  
https://buildlogs.centos.org/c7.1611.u/kernel/20170704132018/3.10.0-514.26.2.el7.x86_64  
https://buildlogs.centos.org/c7.1708.00/kernel/  
https://buildlogs.centos.org/c7.1708.00/kernel/20170822030048/3.10.0-693.el7.x86_64  
https://buildlogs.centos.org/c7.1708.exp.x86_64/kernel/  
https://buildlogs.centos.org/c7.1708.u.x86_64/kernel/  
https://buildlogs.centos.org/c7.1708.u.x86_64/kernel/20170823130501/3.10.0-693.1.1.el7.x86_64  
https://buildlogs.centos.org/c7.1708.u.x86_64/kernel/20170906160426/3.10.0-693.2.1.el7.x86_64  
https://buildlogs.centos.org/c7.1708.u.x86_64/kernel/20170913001530/3.10.0-693.2.2.el7.x86_64  
https://buildlogs.centos.org/c7.1708.u.x86_64/kernel/20171023132245/3.10.0-693.5.2.el7.x86_64  
https://buildlogs.centos.org/c7.1708.u.x86_64/kernel/20171204203818/3.10.0-693.11.1.el7.x86_64  
https://buildlogs.centos.org/c7.1708.u/kernel/  
https://buildlogs.centos.org/c7.1708.u/kernel/20170823130501/3.10.0-693.1.1.el7.x86_64  
https://buildlogs.centos.org/c7.1708.u/kernel/20170906160426/3.10.0-693.2.1.el7.x86_64  
https://buildlogs.centos.org/c7.1708.u/kernel/20170913001530/3.10.0-693.2.2.el7.x86_64  
https://buildlogs.centos.org/c7.1708.u/kernel/20171023132245/3.10.0-693.5.2.el7.x86_64  
https://buildlogs.centos.org/c7.1708.u/kernel/20171204203818/3.10.0-693.11.1.el7.x86_64  
https://buildlogs.centos.org/c7.common/kernel/  

#### 1、系统包
```
	http://mirror.centos.org/centos/6.5/os/x86_64/Packages/
	国内地址
	http://isoredirect.centos.org/centos/6.5/isos/x86_64/  
	ex：
		http://mirror.symnds.com/distributions/CentOS-vault/5.5/isos/x86_64/  
		http://mirrors.stuhome.net/centos/6.5/isos/x86_64/  
		http://mirrors.neusoft.edu.cn/centos/6.5/isos/x86_64/
		http://mirrors.163.com/centos/6.5/isos/x86_64/
		http://mirrors.hust.edu.cn/centos/6.5/isos/x86_64/
		http://centos.ustc.edu.cn/centos/6.5/isos/x86_64/
		http://mirror.bit.edu.cn/centos/6.5/isos/x86_64/
		http://mirrors.tuna.tsinghua.edu.cn/centos/6.5/isos/x86_64/
		http://mirrors.grandcloud.cn/centos/6.5/isos/x86_64/
		http://mirror.neu.edu.cn/centos/6.5/isos/x86_64/
		http://mirrors.btte.net/centos/6.5/isos/x86_64/
		http://mirrors.hustunique.com/centos/6.5/isos/x86_64/
		http://mirrors.aliyun.com/centos/6.5/isos/x86_64/
```

#### 2、debuginfo包：
```
	http://debuginfo.centos.org/6/x86_64/
```

#### 3、src.prm包
```
	ftp://ftp.redhat.com/pub/redhat/linux/enterprise
	ftp://ftp.redhat.com/pub/redhat/linux/enterprise/5Client/en/os/SRPMS/kexec-tools-1.102pre-154.el5.src.rpm
	ftp://ftp.redhat.com/pub/redhat/linux/enterprise/5Client/en/os/SRPMS/kexec-tools-1.102pre-164.el5.src.rpm
	http://vault.centos.org/5.11/os/SRPMS/kexec-tools-1.102pre-165.el5.src.rpm
```

#### 4、各种包
```
	pkgs/org
```

