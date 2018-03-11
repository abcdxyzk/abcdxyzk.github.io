---
layout: post
title: "Linux内核的加密函数"
date: 2018-03-12 02:45:00 +0800
comments: false
categories:
- 2018
- 2018~03
- kernel
- kernel~crypto
tags:
---

http://bbs.chinaunix.net/thread-1984676-1-1.html

  Linux内核支持很多加密算法，包括对称加密算法，如AES；摘要算法，如sha1,md5；压缩算法，如deflate。不过内核好像不支持非对称加密算法。这些算法作为加密函数框架的最底层，提供加密和解密的实际操作。这些函数可以在内核crypto文件夹下，相应的文件中找到。不过内核模块不能直接调用这些函数，因为它们并没有export。内核提供一个统一的框架，来管理这些算法。加密算法通过crypto_register_alg()和crypto_unregister_alg()注册。

内核将加密算法分为三类，1）cipher，2）compress，3）digest。加密函数框架中有相应的API封装，提供给模块调用。

  对于使用这些加密函数，首先通过crypto_alloc_tfm()来分配一个加密函数对象的实例。初始化这些实例，然后就可以通过框架提供的API对数据进行加密和解密。完成以后，必须通过crypto_free_tfm()撤销实例。

下面是几个代码，或许能够够对内核的加密框架有更直观的了解：

#### 1 digest算法（sha1）
```
	#include <linux/kernel.h>
	#include <linux/module.h>
	#include <linux/crypto.h>
	#include <linux/scatterlist.h>
	#include <linux/gfp.h>
	#include <linux/err.h>
	#include <linux/syscalls.h>
	#include <linux/slab.h>
	
	struct crypto_tfm *tfm;
	struct scatterlist sg[1];
	char * code1 = "2ew34123132513451345";
	char * code2 = "234123132513451345";
	
	char *do_digest(char * code) {
		char *result;
		int code_len = strlen(code);
	
		tfm = crypto_alloc_tfm("sha1",0);
		if(IS_ERR(tfm))
			return 0;
		sg_init_one(sg,code,code_len);
	
		crypto_digest_init(tfm);
		crypto_digest_update(tfm,sg,1);
		result = (char *)kmalloc(sizeof(char)*50,GFP_KERNEL);
		if(result == NULL) {
			crypto_free_tfm(tfm);
			return 0;
		}
		memset(result,0,sizeof(char)*50);
		crypto_digest_final(tfm,result);
		crypto_free_tfm(tfm);
		return result;
	}
	
	static int __init test_init(void)
	{
		char *result1,*result2;
		result1 = do_digest(code1);
		if(!result1)
			goto failed2;
		result2 = do_digest(code2);
		if(!result2)
			goto failed1;
	
		if(memcmp(result1,result2,50) != 0)
			printk("<1>code1 != code2\n");
		else
			printk("<1>code1 == code2\n");
		kfree(result2);
	failed1:
		kfree(result1);
	failed2:
		return 0;
	}
	
	static void __exit test_exit(void)
	{
	
	}
	
	module_init(test_init);
	module_exit(test_exit);
	
	MODULE_LICENSE("GPL");
```

#### 2 compress算法（deflate）
```
	#include <linux/module.h>
	#include <linux/kernel.h>
	#include <linux/crypto.h>
	#include <linux/scatterlist.h>
	#include <linux/gfp.h>
	#include <linux/err.h>
	#include <linux/syscalls.h>
	#include <linux/slab.h>
	
	struct crypto_tfm *tfm;
	char * code = "Hello everyone, I'm richardhesidu from chinaunix.net !";
	
	
	static inline void hexdump(unsigned char *buf,unsigned int len) {
		while(len--)
			printk("0x%02x,",*buf++);
		printk("\n");
	}
	
	static int __init test_init(void) {
		int ret,result_len,temp_len;
		char result[512];
		char temp[512];
	
		printk("<1>%s\n",code); 
	 
		/* Allocate transform for deflate */
				
		tfm = crypto_alloc_tfm("deflate",0);
		if(IS_ERR(tfm)) {
			printk("<1>failed to load transform for deflate !\n");
			return 0;
		}
	
		memset(result,0,sizeof(result));
	
		temp_len = 512;
		ret = crypto_comp_compress(tfm,code,strlen(code),temp,&temp_len);
		if(ret) {
			printk("<1>failed to compress !\n");
			return 0;
		}
	
		hexdump(temp,strlen(temp));
	
		memset(result,0,sizeof(result));
	
		result_len = 512;
		ret = crypto_comp_decompress(tfm,temp,strlen(temp),result,&result_len);
		if(ret) {
			printk("<1>failed to decompress !\n");
			return 0;
		}
	
		printk("<1>%s\n",result);
	
		if(memcmp(code,result,strlen(code)) != 0)
			printk("<1>decompressed was not successful\n");
		else
			printk("<1>decompressed was successful\n");
	
		crypto_free_tfm(tfm);
		return 0;
	}
	
	static void __exit test_exit(void)
	{
	
	}
	
	module_init(test_init);
	module_exit(test_exit);
	
	MODULE_LICENSE("GPL");
```

#### 3 cipher算法（aes）

```
	#include <linux/module.h>
	#include <linux/kernel.h>
	#include <linux/crypto.h>
	#include <linux/scatterlist.h>
	#include <linux/gfp.h>
	#include <linux/err.h>
	#include <linux/syscalls.h>
	#include <linux/slab.h>
	#include <linux/highmem.h>
	
	struct crypto_tfm *tfm;
	#if 1
	char *code = "Hello everyone,I'm Richardhesidu"
			"Hello everyone,I'm Richardhesidu"
			"Hello everyone,I'm Richardhesidu";
	
	char *key = "00112233445566778899aabbccddeeff";
	#endif
	
	#if 0
	char code[] = {0x00,0x11,0x22,0x33,0x44,0x55,0x66,0x77,0x88,0x99,0xaa,
			0xbb,0xcc,0xdd,0xee,0xff};
	char key[] = {0x00,0x01,0x02,0x03,0x04,0x05,0x06,0x07,0x08,0x09,0x0a,
			0x0b,0x0c,0x0d,0x0e,0x0f};
	#endif
	
	static inline void hexdump(unsigned char *buf,unsigned int len) {
		while(len--)
			printk("%02x",*buf++);
		printk("\n");
	}
	
	static int __init test_init(void) {
		int ret,templen,keylen,codelen;
		struct scatterlist sg[1];
		char *result;
		char *temp;
	
		keylen = 16;
		codelen = strlen(code)/2;
	#if 0
		printk("<1>%s, codelen=%d\n",code,strlen(code));
		printk("<1>%s, keylen=%d\n",key,strlen(key)); 
	#endif 
		/* Allocate transform for AES ECB mode */
				
		tfm = crypto_alloc_tfm("aes",CRYPTO_TFM_MODE_ECB);
		if(IS_ERR(tfm)) {
			printk("<1>failed to load transform for aes ECB mode !\n");
			return 0;
		}
	
		ret = crypto_cipher_setkey(tfm,key,keylen);
		if(ret) {
			printk("<1>failed to setkey \n");
			goto failed1;
		}
	
		sg_init_one(sg,code,codelen);
			
		/* start encrypt */
	
		ret = crypto_cipher_encrypt(tfm,sg,sg,codelen);
		if(ret) {
			printk("<1>encrypt failed \n");
			goto failed1;
		}
	
		temp = kmap(sg[0].page) + sg[0].offset;
	
		hexdump(temp,sg[0].length);
	
		/* start dencrypt */
		templen = strlen(temp)/2;
		sg_init_one(sg,temp,templen);
		ret = crypto_cipher_decrypt(tfm,sg,sg,templen);
		if(ret) {
			printk("<1>dencrypt failed \n");
			goto failed1;
		}
	
		result = kmap(sg[0].page) + sg[0].offset;
		printk("<1>%s\n",result);
	//	hexdump(result,sg[0].length);
	
	
	#if 0
		if(memcmp(code,result,strlen(code)) != 0)
			printk("<1>dencrpt was not successful\n");
		else
			printk("<1>dencrypt was successful\n");
	#endif
	failed1:
		crypto_free_tfm(tfm);
		return 0;
	}
	
	static void __exit test_exit(void)
	{
	
	}
	
	module_init(test_init);
	module_exit(test_exit);
	
	MODULE_LICENSE("GPL");
```
