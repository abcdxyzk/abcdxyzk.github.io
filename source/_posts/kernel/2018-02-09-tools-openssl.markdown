---
layout: post
title: "openssl常用命令，签名、非对称加解密"
date: 2018-02-09 00:38:00 +0800
comments: false
categories:
- 2018
- 2018~02
- kernel
- kernel~signature
tags:
---

http://blog.csdn.net/modianwutong/article/details/43059435

https://www.cnblogs.com/gordon0918/p/5363466.html

https://stackoverflow.com/questions/5140425/openssl-command-line-to-verify-the-signature

-------------


### 证书

  证书是一个经证书授权中心签过名的包含公钥及公钥拥有者信息的文件。证书授权中心（CA）对证书签名的过程即为证书的颁发过程。证书里面的公钥只属于某一个实体（网站，个人等），它的作用是防止一个实体伪装成另外一个实体。

 证书可以保证非对称加密算法的合理性，假设A和B的通话过程如下：

```
 A -------> Hello (plain text) ---------> B
 A <------- Hello (plain text) ---------- B
 A <------ B Send Certificate to A --- B
 A ------- cipher text ------------------> B
 A <------ cipher text ------------------- B
  … …
```

  A在接受了B发过来的证书以后，A，B就可以使用密文进行通信了。


  如果C想伪装成B，应该怎么做呢？我们想象下面的通话过程：
```
 A-------> Hello (plain text) ---------> C
 A <------ Hello (plain text) ----------- C
 A <------ C Send Certificate to A --- C
```
 
  此时A没有怀疑C的身份，理所当然的接受了C的证书，并继续进行下面的通信

```
 A------- cipher text ------------------> C
 A <------ cipher text ------------------- C
  … …
```
 

  这样的情况下A是没有办法发现C是假的，A的用户名，密码，甚至卡号等重要信息都有可能被C截获。如果A在通话过程中要求取得B的证书，并验证证书里面的名字，如果发现名字与B不符合，就可以发现对方不是B。验证B的名字通过后，再继续通信过程。

  那么，如果证书是假的怎么办？或者证书被修改过怎么办？此时就要用到签名信息了。数字证书除了包含证书拥有者的名字和公钥外，还应包含颁发证书的机构名称，证书序列号和其它一些可选信息。最重要的是，它包含了证书颁发机构（Certification Authority，简称CA）的签名信息。

  通过检查证书里面CA的签名信息，就知道这个证书的确是由该CA签发的，然后你就可以检查证书里面的证书拥有者的名字，检查通过后，就可以提取公钥，继续通信了。这样做的基础是，你信任该CA，认为该CA没有颁发错误的证书。

  CA是第三方机构，被你信任，由它保证证书的确发给了应该得到证书的人。这里需要解释一下，CA也是一个实体，它也有自己的公钥和私钥，否则怎么做数字签名？它也有自己的证书，你可以去它的站点下载它的证书，来验证签名。

  CA也是分级别的，最高级别的CA叫RootCA，低一级别的CA的证书由它来颁发和签名。这样我们信任RootCA，那些由RootCA签名过的证书的CA就可以来颁发证书给实体或其它CA了。那RootCA谁给签名呢？他们自己给自己签名，叫自签名。

  现在常用的证书都是采用X.509格式的，这是一个国际标准的证书格式。任何遵循该标准的应用程序都可以读，写X509格式的证书。

  下面是一个证书的例子：

```
Certificate:
   Data:
       Version: 3 (0x2)
       Serial Number: 17209717939030827578 (0xeed53348d899a23a)
   Signature Algorithm: sha1WithRSAEncryption                             // 签名算法
       Issuer: C=AU, ST=Some-State, O=Internet Widgits Pty Ltd            // 证书颁发者信息
       Validity
           Not Before: Jan 14 07:01:20 2015 GMT                           // 证书的有效期
           Not After : Feb 13 07:01:20 2015 GMT
       Subject: C=AU, ST=Some-State, O=Internet Widgits Pty Ltd           // 证书拥有者信息
       Subject Public Key Info:                                           //公钥信息
           Public Key Algorithm: rsaEncryption
                Public-Key: (512 bit)
                Modulus:
                   00:c5:63:8c:c8:32:b1:2e:15:58:a6:cd:22:f4:40:
                   ef:53:8e:e7:fa:4e:fd:d5:d9:fe:69:a2:c2:5a:fc:
                   20:4b:da:c9:17:49:35:4e:67:92:82:ec:4e:a7:a7:
                   1a:66:3a:c5:36:2e:74:77:30:7a:dd:65:5f:03:9a:
                   9b:2e:d0:b1:43
                Exponent: 65537 (0x10001)
       X509v3 extensions:                                                 //x509扩展信息
           X509v3 Subject Key Identifier:
               0E:DB:FE:E6:CF:FE:A6:C8:6D:38:06:A5:22:34:DA:82:A9:BE:42:B8
           X509v3 Authority Key Identifier:
               keyid:0E:DB:FE:E6:CF:FE:A6:C8:6D:38:06:A5:22:34:DA:82:A9:BE:42:B8
           X509v3 Basic Constraints:
               CA:TRUE
   Signature Algorithm: sha1WithRSAEncryption
        32:43:0c:e8:32:6f:30:10:c9:0f:a3:36:24:7c:a5:dc:da:8c:            // CA的签名
        c4:90:69:90:de:b1:1b:19:8e:b1:a5:35:ce:2e:7a:05:69:94:
        46:72:37:c2:2c:38:57:4a:0c:89:bc:90:95:03:af:f2:6f:a0:
        3f:13:5f:f0:90:a7:2c:bf:75:ee
```
 
### 算法

  加密算法分为两种：对称加密算法和非对称加密算法；

#####  对称加密算法：即信息的发送方和接收方使用同一个密钥去加密和解密数据。它的最大优势是加/解密速度快，适合于对大数据量进行加密，但密钥管理困难。AES，DES等都是常用的对称加密算法；

#####  非对称加密算法：它需要使用不同的密钥来分别完成加密和解密操作，一个公开发布，即公开密钥，另一个由用户自己秘密保存，即私用密钥。信息发送者用公开密钥去加密，而信息接收者则用私用密钥去解密。公钥机制灵活，但加密和解密速度却比对称密钥加密慢得多。RSA，DSA等是常用的非对称加密算法；

  所以在实际的应用中，人们通常将两者结合在一起使用，例如，对称密钥加密系统用于存储大量数据信息，而公开密钥加密系统则用于加密密钥。

  另外还有一种我们需要知道的加密算法，叫做摘要算法，英文名是messagedigest，用来把任何长度的明文以一定规则变成固定长度的一串字符。那么我们在对文件做签名的时候，通常都是先使用摘要算法，获得固定长度的一串字符，然后对这串字符进行签名。

  Base64不是加密算法，它是编码方式，用来在ASCII码和二进制码之间进行转换。

### RSA

  RSA是目前比较流行的非对称加密算法，所谓非对称，就是指该算法需要一对密钥，使用其中一个加密，则需要用另一个才能解密。下面简单介绍一下它的原理：

  RSA的算法涉及三个参数，n、e1、e2。

  其中，n是两个大质数p、q的积，n的二进制表示所占用的位数，就是所谓的密钥长度。

  e1和e2是一对相关的值，e1可以任意取，但要求e1与(p-1）*(q-1）互质；再选择e2，要求(e2*e1) mod((p-1)*(q-1))=1。

  (n，e1), (n，e2)就是密钥对。其中(n，e1)为公钥，(n，e2)为私钥。

  RSA加解密的算法完全相同，设A为明文，B为密文，则：A=B^e2 mod n；B=A^e1 mod n；（公钥加密体制中，一般用公钥加密，私钥解密）

  e1和e2可以互换使用，即：

  A=B^e1mod n；B=A^e2 mod n;

  关于RSA加密算法的详细介绍可以参考百度百科；

### 协议

  SSL是Secure Sockets Layer（安全套接层协议）的缩写，可以在Internet上提供秘密性传输。Netscape公司在推出第一个Web浏览器的同时，提出了SSL协议标准。其目标是保证两个应用间通信的保密性和可靠性,可在服务器端和用户端同时实现支持。已经成为Internet上保密通讯的工业标准。

  OpenSSL是一个强大的安全套接字层密码库，囊括主要的密码算法、常用的密钥和证书封装管理功能及SSL协议，并提供丰富的应用程序供测试或其它目的使用。

  OpenSSL整个软件包大概可以分成三个主要的功能部分：SSL协议库、应用程序以及密码算法库。OpenSSL的目录结构自然也是围绕这三个功能部分进行规划的。

  Openssl目前最新的稳定版本是1.0.2，可以在openssl的官网下载openssl-1.0.2的源代码。

  Android中已经集成了openssl，源码目录在：external/openssl

### 工具

  RSA是目前最有影响力的公钥加密算法，它能抵抗到目前为止已知的绝大多数密码攻击。下面我们要介绍的工具将会主要涉及到秘钥的产生、管理，证书请求及证书的产生，数据加密、解密，算法签名及身份验证等；

#### genrsa

生成RSA私有密钥，用法如下：
```
	openssl genrsa [-outfilename] [-passout arg] [-aes128] [-aes192] [-aes256] [-des] [-des3] [-idea][-f4] [-3]
	[-rand file(s)] [-engine id] [numbits]

	Options：
	  -outfilename：将生成的私钥输出到指定文件，默认为标准输出；
	  -passoutarg：如果对生成的密钥使用加密算法的话，可以使用”-passout”选项指定密码来源，这里的arg可以是”pass:password”，”file:pathname”，”stdin”等；
	  -des|-des3|-idea：采用什么加密算法来加密生成的密钥，一般需要输入保护密码；
	  -f4|3：生成密钥过程中使用的公共组件，65537或3，默认使用65537；
	  numbits：密钥长度，必须是genrsa命令的最后一个参数，默认值是512；
```

注意：genrsa生成的私钥默认是PEM编码格式；

##### Example：

1）生成私钥：
```
	openssl genrsa -out private.pem -3 -2048
```
#### rsa

RSA密钥管理工具，用法如下：

```
	openssl rsa [-informPEM|NET|DER] [-outform PEM|NET|DER] [-in filename] [-passin arg] [-outfilename] [-passout arg] [-sgckey] [-aes128] [-aes192] [-aes256] [-des] [-des3][-idea] [-text] [-noout] [-modulus] [-check] [-pubin] [-pubout]

	Options：
	  -informPEM|NET|DER：指定输入格式，可以是PEM，NET或DER格式；
	  -outformPEM|NET|DER：指定输出格式，可以是PEM，NET或DER格式；
	  -infilename：指定输入文件，如果是加密过的密钥，会要求你输入密码；
	  -outfilename：指定输出文件名称；
	  -passinarg：输入密钥的密码来源；
	  -passoutarg：输出密钥如果加密的话，该参数用于指定密码的来源；
	  -text：以只读方式输出密钥及组件信息；
	  -noout：不打印密钥文件；
	  -modulus：显示密钥的模数；
	  -pubin：默认从输入文件中读取私钥，如果该选项打开的话，就可以从输入文件中读取公钥；
	  -pubout：默认输出私钥，使用该选项的话，将会输出公钥，如果pubin打开的话，pubout也会被自动打开；另外使用该参数的话，可以由私钥导出公钥；
	  -check：检查RSA密钥是否被破坏；
```

Example：

1）去掉私钥的保护密码
```
	openssl rsa -in private.pem -out private_out.pem
```

2）用DES3算法加密密钥，需要输入保护密码
```
	openssl rsa -in private.pem -des3 -out private_out.pem
```

3）把私钥文件从PEM格式转换为DER格式
```
	openssl rsa -in private.pem -outform DER -out private.der
```

4）打印私钥组件信息
```
	openssl rsa -in private.pem -text -noout
```

5）导出公钥文件
```
	openssl rsa -in private.pem -out public.pem -pubout
```

6）检查密钥文件的完整性
```
	openssl rsa -in private.pem -check -noout
```

#### rsautil

 无论是使用公钥加密还是私钥加密，RSA每次能够加密的数据长度不能超过RSA密钥长度，并且根据具体的补齐方式不同输入的加密数据最大长度也不一样，而输出长度则总是跟RSA密钥长度相等。RSA不同的补齐方法对应的输入输入长度如下表
```
	数据补齐方式		输入数据长度		输出数据长度	参数字符串
	PKCS#1 v1.5		少于(密钥长度-11)字节	同密钥长度	-pkcs
	PKCS#1 OAEP		少于(密钥长度-11)字节	同密钥长度	-oaep
	PKCS#1 for SSLv23	少于(密钥长度-11)字节	同密钥长度	-ssl
	不使用补齐		同密钥长度		同密钥长度	-raw
```

RSA工具指令，包含RSA算法签名，身份验证，加密/解密数据等功能，用法如下：
```
	openssl rsautl [-in file][-out file] [-inkey file] [-pubin] [-certin] [-sign] [-verify] [-encrypt][-decrypt] [-pkcs]
	[-ssl] [-raw] [-hexdump] [-asn1parse]

	Options：
	  -infile：指定输入文件；
	  -outfile：指定输出文件；
	  -inkeyfile：加密/解密数据时使用的密钥文件，缺省使用RSA私钥；
	  -pubin：如果设置该选项，则使用RSA公钥加密/解密数据；
	  -certin：指定使用证书文件加密/解密，证书中包含RSA公钥；
	  -sign：数据签名，需要指定RSA私钥；
	  -verify：数据验证；
	  -encrypt：数据加密；
	  -decrypt：数据解密；
	  -pkcs|-ssl|-raw：指定数据填充模式，分别代表：PKCS#1v1.5（缺省值），SSL v2填充模式或无填充。如果要签名，只能使用pkcs或raw选项；
	  -hexdump：用十六进制输出数据；
	  -asn1parse：按照ASN.1结构分析输出数据，详细可以查看asn1parse命令，和”-verify”选项一起使用威力强大；
```

Example：

1）公钥加密
```
	openssl rsautl -encrypt -in test.txt -out test.enc -inkey public.pem -pubin
```
注意：如果使用证书加密的话，需要使用-certin选项，-inkey指定证书文件；

2）私钥解密
```
	openssl rsautl -decrypt -in test.enc -out test.dec -inkey private.pem
```

3）私钥签名
```
	openssl rsautl -sign -in test.txt -out test.sig -inkey private.pem
```

4）公钥验证
```
	openssl rsautl -verify -in test.sig -out test.vfy -inkey public.pem -pubin
```
注意：如果使用证书验证签名，需要使用-certin选项，-inkey指定证书文件；

#### req

创建和处理证书请求的工具，它还能建立自签名的证书，做RootCA用。用法如下：
```
	openssl req [-inform PEM|DER][-outform PEM|DER] [-in filename] [-passin arg] [-out filename] [-passout arg][-text] [-pubkey] [-noout] [-verify] [-modulus] [-new] [-rand file(s)] [-newkeyrsa:bits] [-newkey alg:file] [-nodes] [-key filename] [-keyform PEM|DER][-keyout filename] [-keygen_engine id] [-[digest]] [-config filename] [-subjarg] [-multivalue-rdn] [-x509] [-days n] [-set_serial n] [-asn1-kludge][-no-asn1-kludge] [-newhdr] [-extensions section] [-reqexts section] [-utf8][-nameopt] [-reqopt] [-subject] [-subj arg] [-batch] [-verbose]


	在创建证书请求（CSR）的过程中会要求用户输入一些必要的信息，包括位置、组织、邮箱等信息，可以把它们放在配置文件里，也可以放在命令行参数里；

	Options：
	  -text：以可读方式打印将CSR文件里的内容；
	  -noout：不要打印CSR文件的编码信息；
	  -pubkey：解析CSR文件里的公钥信息，并打印出来；
	  -modulus：解析CSR文件里的公钥模数，并打印出来；
	  -verify：检验请求文件的签名信息；
	  -new：创建新的CSR，它会要求用户输入一些必要信息。如果没有指定-key参数，将会生成新的私钥；
	  -newkeyarg：创建新的CSR和新的私钥，参数指示私钥算法等信息（类似rsa:1024）；
	  -keyfilename：指定私钥文件，该选项支持pkcs8格式的私钥；
	  -keyoutfilename：如果产生了新的私钥，则把私钥保存到指定文件；
	  -[digest]：指定证书的摘要算法，可以是sha1|md5等，默认使用哈希算法；
	  -configfilename：指定config文件，配置文件可以包含创建CSR所需的各种信息；
	  -x509：产生自签名的证书，而不是证书请求；
	  -days：证书的有效期，缺省是30天；
	  -subject：显示CSR的位置，组织等信息；
	  -subjarg：设置CSR的位置，组织等信息，格式：/C=AU/ST=Some-State/type0=value0
```
 

关于配置文件的格式，可以参考openssl的在线帮助文档；


Example：

1）生成一个私钥，并使用该私钥产生证书请求
```
	openssl genrsa -out private.pem 1024

	openssl req -new -key private.pem -out req.pem
```
如果加上-sha256，则使用sha256摘要算法；


2）生成一个私钥，并使用该私钥产生证书请求，另一个方法
```
	openssl req -newkey rsa:2048 -keyout private.pem -out req.pem
```

3）检测并验证证书请求
```
	openssl req -verify -in req.pem -text -noout
```

4）制作自签名的根证书，使用已有私钥
```
	openssl req -new -x509 -key private.pem -sha256 -out rootca.crt
```

5）制作自签名的根证书，创建新的私钥
```
	openssl req -newkey rsa:2048 -keyout private.pem -x509 -sha256 -out rootca.crt
```
 
#### x509

证书管理工具，可以用来显示证书的内容，证书格式转换，为证书请求签名等。用法如下：

```
	openssl x509 [-inform DER|PEM|NET][-outform DER|PEM|NET] [-keyform DER|PEM]

	[-CAform DER|PEM] [-CAkeyform DER|PEM][-in filename] [-out filename] [-serial] [-hash] [-subject_hash] [-issuer_hash][-ocspid] [-subject] [-issuer] [-nameopt option] [-email] [-ocsp_uri][-startdate] [-enddate] [-purpose] [-dates] [-checkend num] [-modulus][-pubkey] [-fingerprint] [-alias] [-noout] [-trustout] [-clrtrust] [-clrreject][-addtrust arg] [-addreject arg] [-setalias arg] [-days arg] [-set_serial n][-signkey filename] [-passin arg] [-x509toreq] [-req] [-CA filename] [-CAkeyfilename] [-CAcreateserial] [-CAserial filename] [-force_pubkey key] [-text][-certopt option] [-C] [-md2|-md5|-sha1|-mdc2] [-clrext] [-extfile filename][-extensions section] [-engine id]

	Options：

	由于x509的选项比较多，下面我们来分类介绍：

	1）输入/输出相关选项
	  -inform|-outformDER|PEM|NET：指定输入/输出文件的编码格式；
	  -in|-outfilename：指定输入/输出文件；
	  -md2|-md5|-sha1|-mdc2：指定摘要算法，默认使用哈希算法；

	2）显示选项
	  -text：以可读方式打印证书内容；
	  -pubkey：解析证书里面的公钥信息，并打印出来；
	  -modulus：解析证书里面的公钥模数信息，并打印出来；
	  -serial：显示证书的序列号；
	  -subject：显示证书拥有者的信息；
	  -issuer：显示证书颁发者的名字；
	  -email：显示邮箱信息；
	  -startdate|-enddate |-dates：显示证书有效期，起始和结束时间；
	  -fingerprint：显示DER格式证书的DER版本信息；
	  -C：以C语言源文件方式，输出证书；

	3）信任相关的选项
	  -purpose：显示证书用途；

	4）签名相关选项
	  -signkeyfilename：使用给定的私钥，对输入文件进行签名；如果输入文件是证书，那么它的颁发者就会被设置成其拥有者，其它项也会被设置成符合自签名特征的证书项；如果输入文件是证书请求，那么就会产生一个自签名的证书；
	  -days：证书有效期；
	  -x509toreq：把证书转换成证书请求，需要-signkey选项指定私钥；
	  -req：默认输入文件是一个证书，使用该选项的话，指示输入文件是一个证书请求；
	  -CAfilename：指定签名用的CA证书文件，该选项通常和-req一起用，对证书请求实施自签名；
	  -CAkeyfilename：指定CA的私钥文件，对证书进行签名；
```
 

Examples：

1）显示证书内容
```
	openssl x509 -in cert.pem –noout -text
```

2）显示证书序列号
```
	openssl x509 -in cert.pem -noout -serial
```

3）显示证书的subject信息
```
	openssl x509 -in cert.pem -noout -subject
```

4）显示证书的fingerprint信息
```
	openssl x509 -in cert.pem -noout -fingerprint
```

5）证书格式转换
```
	openssl x509 -in cert.pem -inform PEM -out cert.der -outform DER

	openssl x509 -in cert.der -inform DER -out cert.pem -outform PEM
```
 

6）把证书转换为证书请求
```
	openssl x509 -x509toreq -in cert.pem -out cert.csr -signkey private.pem
```

7）把证书请求转换为证书
```
	openssl x509 -req -in careq.pem -extfile openssl.cnf -extensions v3_ca -signkey key.pem -out cacert.pem
```

8）从证书里面提取公钥
```
	openssl x509 -pubkey -in cert.pem >pub.pem
```

9）以C语言源文件方式输出证书，PEM格式转换为DER格式
```
	openssl x509 -in cert.pem -out cert.der -outform DER -C > cert.c
```
 
#### asn1parse

asn1parse是一个诊断工具，可以解析ASN.1结构的密钥，证书等，用法如下：
```
	openssl asn1parse [-informPEM|DER] [-in filename] [-out filename] [-noout] [-offset number]
	[-length number] [-i] [-oid filename][-dump] [-dlimit num] [-strparse offset] [-genstr string]
	[-genconf file] [-strictpem]

	Options：
	  -i：以缩进方式显示ASN.1结构
```

Example：

1）解析文件：
```
	openssl asn1parse -in file.pem
```

2）解析DER格式的文件
```
	openssl asn1parse -inform file.der -infile.der
```

#### dgst

摘要算法，用法如下：

```
	openssl dgst[-sha|-sha1|-mdc2|-ripemd160|-sha224|-sha256|-sha384|-sha512|-md2|-md4|-md5|-dss1][-c] [-d] [-hex] [-binary] [-r] [file...]

	所谓摘要算法，就是把任何长度的明文以一定规则变成固定长度的一串字符。

	Options：
	  -sha|-sha1 |-sha256 |-md5：算法名称；
	  -c：打印出哈希结果的时候用冒号来分隔开；
	  -d：详细打印出调试信息；
	  -hex：以十六进制输出哈希结果，默认值；
	  -binary：以二进制输出哈希结果；
	  file：要哈希的文件；
```
 

Example：

1）使用sha256摘要算法，并以二进制方式输出
```
	openssl dgst -sha256 -binary file.txt
```

#### PEM 和 DER 格式

 PEM和DER是两种不同的编码格式，PEM使用ASCII（base64）编码方式，PEM格式文件的第一行和最后一行指明文件内容，DER采用二进制编码格式；使用openssl工具生成的密钥或证书，默认使用PEM编码格式；

### 实例

#### Image签名

高通平台支持bootimg签名和校验功能，下面我们先来看看它的签名脚本：

```
	define build-boot-image
	 mv -f $(1) $(1).nonsecure
	 openssl dgst -$(TARGET_SHA_TYPE) -binary $(1).nonsecure >$(1).$(TARGET_SHA_TYPE)
	 openssl rsautl -sign -in $(1).$(TARGET_SHA_TYPE) -inkey$(PRODUCT_PRIVATE_KEY) -out $(1).sig
	 dd if=/dev/zero of=$(1).sig.padded bs=$(BOARD_KERNEL_PAGESIZE) count=1
	 dd if=$(1).sig of=$(1).sig.padded conv=notrunc
	 cat $(1).nonsecure $(1).sig.padded > $(1).secure
	 rm -rf $(1).$(TARGET_SHA_TYPE) $(1).sig $(1).sig.padded
	 mv -f $(1).secure $(1)
	endef
```

这里定义了一个build-boot-image宏，使用方法如下：

```
	$(INSTALLED_BOOTIMAGE_TARGET) :=$(PRODUCT_OUT)/boot.img
	INSTALLED_SEC_BOOTIMAGE_TARGET :=$(PRODUCT_OUT)/boot.img.secure
	$(INSTALLED_SEC_BOOTIMAGE_TARGET):$(INSTALLED_BOOTIMAGE_TARGET)
	   $(hide) $(callbuild-boot-image,$(INSTALLED_BOOTIMAGE_TARGET))
```

上面就是对bootimg实施签名操作的脚本，build-boot-image宏做了什么事呢？

1）对bootimg实施摘要算法
```
	openssl dgst -sha256 -binary boot.img.nonsecure > boot.img.sha256
```
2）制作签名
```
	openssl rsautl -sign -inboot.img.sha256 -inkey qcom.key -out boot.img.sig
```
3）填充签名

```
	dd if=/dev/zero of=boot.img.sig.paddedbs=2048 count=1
	dd if=boot.img.sig of=boot.img.sig.paddedconv=notrunc
```
生成好的签名已经保存到boot.img.sig.padded，文件大小是2048字节，用0填充；

4）把签名打到image上，并删除中间文件

```
	cat boot.img.nonsecure boot.img.sig.padded > boot.img.secure
	rm -rf $boot.img.sha256 boot.img.sigboot.img.sig.padded

	mv -f boot.img.secure boot.img
```

Image签名完成，签名信息保存在image的最后2048字节，不足的都用0来填充；


验证签名又是怎样的过程呢？前面用到了qcom.key，这个是高通提供的私钥，那么要验证签名，我们就需要用这个私钥制作的证书。验证过程是在LK里面完成的，所以我们还需要把证书转换为C源文件方式，方法很简单，用下面几条命令就可以了：

```
	openssl req -new -x509 -key qcom.key -days11324 -sha256 -out qcom.crt

	openssl x509 -inform PEM -in qcom.crt-outform DER -out qcom.der > cert.c
```

好了，cert.c就是以C源文件方式存储的证书，替换到LK里面相应的数组就可以了；

 
#### 制作testkey

 testkey是用来给apk签名的，保存在build/target/product/security目录下，testkey.pk8是私钥，

testkey.x509.pem是证书，那么这个key和证书是如何生成的呢？README写的很清楚：
```
	development/tools/make_key testkey '/C=US/ST=California/L=MountainView/O=Android/OU=Android/
	 CN=Android/emailAddress=android@android.com'
```

development/tools/make_key是制作testkey的脚本文件，我们看几条比较重要的命令吧：

1）生成私钥
```
	openssl genrsa -f4 2048 | tee ${one}> ${two}
```
2）制作证书
```
	openssl req -new -x509 ${hash} -key${two} -out $1.x509.pem -days 10000 -subj "$2"
```
3）制作pk8格式私钥
```
	openssl pkcs8 -in ${one} -topk8-outform DER -out $1.pk8 –nocrypt
```

### Help

 OpenSSL提供了非常丰富的帮助信息，如果对哪个命令或参数不了解，可以很方便的使用帮助来查看命令的详细用法；
命令列表

使用下面命令查看openssl支持的命令和算法列表：

```
	openssl [ list-standard-commands | list-message-digest-commands | list-cipher-commands | 
              list-cipher-algorithms | list-message-digest-algorithms | list-public-key-algorithms]
	list-standard-commands：标准命令列表
	list-message-digest-commands：摘要命令列表
	list-cipher-commands：加密命令列表
	list-cipher-algorithms：加密算法列表
	list-message-digest-algorithms：摘要算法列表
	list-public-key-algorithms：公钥加密算法列表
```

### 获取帮助

 虽然openssl并不支持”-h”参数，使用”-h”参数，会出现类似” unknown option -h”的错误提示，但是openssl的帮助系统还是很nice的，在使用错误参数的情况下，会把命令的详细用法及参数解释列出来，因此，还是可以用”openssl command -h”的方式来获取帮助信息。

 我们还可以使用类似”man genrsa”的方法，来查看openssl的帮助文档；

 最后，我们还可以访问openssl的主页，里面有详细的帮助文档；

 
### 参考资料

openssl帮助文档：http://www.openssl.org/docs/

openssl命令详解.pdf

百度百科：openssl，RSA，X509；

使用openssl命令剖析RSA私钥文件格式：http://blog.csdn.net/lucien_cc/article/details/18407451

常用证书格式切换：http://blog.chinaunix.net/uid-20553497-id-2353867.html

