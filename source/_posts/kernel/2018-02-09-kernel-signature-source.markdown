---
layout: post
title: "内核模块签名--详解"
date: 2018-02-09 02:33:00 +0800
comments: false
categories:
- 2018
- 2018~02
- kernel
- kernel~signature
tags:
---

### 验证模块签名

kernel/module.c

```
	SYSCALL_DEFINE3(init_module, void __user *, umod,
			unsigned long, len, const char __user *, uargs)
	{
		int err; 
		struct load_info info = { }; 

		err = may_init_module();
		if (err)
			return err; 

		pr_debug("init_module: umod=%p, len=%lu, uargs=%p\n",
		       umod, len, uargs);

		err = copy_module_from_user(umod, len, &info);
		if (err)
			return err; 

		return load_module(&info, uargs, 0);
	}
```


```
	/* Allocate and load the module: note that size of section 0 is always
	   zero, and we rely on this for optional sections. */
	static int load_module(struct load_info *info, const char __user *uargs,
			       int flags)
	{
		struct module *mod;
		struct module_ext *mod_ext;
		long err;

		err = module_sig_check(info);
		if (err)
			goto free_copy;
		...
```

```
	static int module_sig_check(struct load_info *info)
	{
		int err = -ENOKEY;
		const unsigned long markerlen = sizeof(MODULE_SIG_STRING) - 1;
		const void *mod = info->hdr;

		# 模块最后是 MODULE_SIG_STRING 字符串
		if (info->len > markerlen &&
		    memcmp(mod + info->len - markerlen, MODULE_SIG_STRING, markerlen) == 0) {
			/* We truncate the module to discard the signature */
			info->len -= markerlen;
			err = mod_verify_sig(mod, &info->len); // 检验签名
		}

		if (!err) {
			info->sig_ok = true;
			return 0;
		}

		/* Not having a signature is only an error if we're strict. */
		if ((err == -ENOKEY && !sig_enforce) && (get_securelevel() <= 0))
			err = 0;

		return err;
	}
```

```
	/*
	 * Verify the signature on a module.
	 */
	int mod_verify_sig(const void *mod, unsigned long *_modlen)
	{
		struct public_key_signature *pks;
		struct module_signature ms;
		struct key *key;
		const void *sig;
		size_t modlen = *_modlen, sig_len;
		int ret;

		pr_devel("==>%s(,%zu)\n", __func__, modlen);

		if (modlen <= sizeof(ms))
			return -EBADMSG;

		# 去除 MODULE_SIG_STRING 后，文件末尾是定义个字段长度的结构
		memcpy(&ms, mod + (modlen - sizeof(ms)), sizeof(ms));
		modlen -= sizeof(ms);

		# 签名长度
		sig_len = be32_to_cpu(ms.sig_len);
		if (sig_len >= modlen)
			return -EBADMSG;
		modlen -= sig_len;

		# 签名者长度，签名的标识ID长度，ID一般是一个20B的串
		if ((size_t)ms.signer_len + ms.key_id_len >= modlen)
			return -EBADMSG;
		modlen -= (size_t)ms.signer_len + ms.key_id_len;

		*_modlen = modlen;
		sig = mod + modlen;

		/* For the moment, only support RSA and X.509 identifiers */
		if (ms.algo != PKEY_ALGO_RSA ||
		    ms.id_type != PKEY_ID_X509)
			return -ENOPKG;

		if (ms.hash >= PKEY_HASH__LAST ||
		    !hash_algo_name[ms.hash])
			return -ENOPKG;

		# 查找 .system_keyring
		key = request_asymmetric_key(sig, ms.signer_len,
					     sig + ms.signer_len, ms.key_id_len);
		if (IS_ERR(key))
			return PTR_ERR(key);

		# 摘要
		pks = mod_make_digest(ms.hash, mod, modlen);
		if (IS_ERR(pks)) {
			ret = PTR_ERR(pks);
			goto error_put_key;
		}

		# hash算法的额外前缀
		ret = mod_extract_mpi_array(pks, sig + ms.signer_len + ms.key_id_len,
					    sig_len);
		if (ret < 0)
			goto error_free_pks;

		# 验证签名
		ret = verify_signature(key, pks);
		pr_devel("verify_signature() = %d\n", ret);

	error_free_pks:
		mpi_free(pks->rsa.s);
		kfree(pks);
	error_put_key:
		key_put(key);
		pr_devel("<==%s() = %d\n", __func__, ret);
		return ret;
	}
```

```
	/*
	 * Request an asymmetric key.
	 */
	static struct key *request_asymmetric_key(const char *signer, size_t signer_len,
						  const u8 *key_id, size_t key_id_len)
	{
		key_ref_t key;
		size_t i;
		char *id, *q;

		pr_devel("==>%s(,%zu,,%zu)\n", __func__, signer_len, key_id_len);

		/* Construct an identifier. */
		id = kmalloc(signer_len + 2 + key_id_len * 2 + 1, GFP_KERNEL);
		if (!id)
			return ERR_PTR(-ENOKEY);

		memcpy(id, signer, signer_len);

		q = id + signer_len;
		*q++ = ':';
		*q++ = ' ';
		for (i = 0; i < key_id_len; i++) {
			*q++ = hex_asc[*key_id >> 4];
			*q++ = hex_asc[*key_id++ & 0x0f];
		}

		*q = 0;

		pr_debug("Look up: \"%s\"\n", id);

	#ifdef CONFIG_SYSTEM_BLACKLIST_KEYRING
		key = keyring_search(make_key_ref(system_blacklist_keyring, 1),
					   &key_type_asymmetric, id);
		if (!IS_ERR(key)) {
			/* module is signed with a cert in the blacklist.  reject */
			pr_err("Module key '%s' is in blacklist\n", id);
			key_ref_put(key);
			kfree(id);
			return ERR_PTR(-EKEYREJECTED);
		}
	#endif

		key = keyring_search(make_key_ref(system_trusted_keyring, 1),
				     &key_type_asymmetric, id);
		if (IS_ERR(key))
			pr_warn("Request for unknown module key '%s' err %ld\n",
				id, PTR_ERR(key));
		kfree(id);

		if (IS_ERR(key)) {
			switch (PTR_ERR(key)) {
				/* Hide some search errors */
			case -EACCES:
			case -ENOTDIR:
			case -EAGAIN:
				return ERR_PTR(-ENOKEY);
			default:
				return ERR_CAST(key);
			}
		}

		pr_devel("<==%s() = 0 [%x]\n", __func__, key_serial(key_ref_to_ptr(key)));
		return key_ref_to_ptr(key);
	}
```

### 内核密匙注册

#### kernel/system_keyring.c
```
	#include <linux/export.h>
	#include <linux/kernel.h>
	#include <linux/sched.h>
	#include <linux/cred.h>
	#include <linux/err.h>
	#include <keys/asymmetric-type.h>
	#include <keys/system_keyring.h>
	#include "module-internal.h"

	struct key *system_trusted_keyring;
	EXPORT_SYMBOL_GPL(system_trusted_keyring);
	#ifdef CONFIG_SYSTEM_BLACKLIST_KEYRING
	struct key *system_blacklist_keyring;
	#endif

	extern __initconst const u8 system_certificate_list[];
	extern __initconst const unsigned long system_certificate_list_size;

	/*
	 * Load the compiled-in keys
	 */
	static __init int system_trusted_keyring_init(void)
	{
		pr_notice("Initialise system trusted keyring\n");

		system_trusted_keyring =
			keyring_alloc(".system_keyring",
				      KUIDT_INIT(0), KGIDT_INIT(0), current_cred(),
				      ((KEY_POS_ALL & ~KEY_POS_SETATTR) |
				      KEY_USR_VIEW | KEY_USR_READ | KEY_USR_SEARCH),
				      KEY_ALLOC_NOT_IN_QUOTA, NULL);
		if (IS_ERR(system_trusted_keyring))
			panic("Can't allocate system trusted keyring\n");

		set_bit(KEY_FLAG_TRUSTED_ONLY, &system_trusted_keyring->flags);

	#ifdef CONFIG_SYSTEM_BLACKLIST_KEYRING
		system_blacklist_keyring = keyring_alloc(".system_blacklist_keyring",
					    KUIDT_INIT(0), KGIDT_INIT(0),
					    current_cred(),
					    (KEY_POS_ALL & ~KEY_POS_SETATTR) |
					    KEY_USR_VIEW | KEY_USR_READ,
					    KEY_ALLOC_NOT_IN_QUOTA, NULL);
		if (IS_ERR(system_blacklist_keyring))
			panic("Can't allocate system blacklist keyring\n");

		set_bit(KEY_FLAG_TRUSTED_ONLY, &system_blacklist_keyring->flags);
	#endif

		return 0;
	}

	/*
	 * Must be initialised before we try and load the keys into the keyring.
	 */
	device_initcall(system_trusted_keyring_init);

	/*
	 * Load the compiled-in list of X.509 certificates.
	 */
	static __init int load_system_certificate_list(void)
	{
		key_ref_t key;
		const u8 *p, *end;
		size_t plen;

		pr_notice("Loading compiled-in X.509 certificates\n");

		p = system_certificate_list;
		end = p + system_certificate_list_size;
		while (p < end) {
			/* Each cert begins with an ASN.1 SEQUENCE tag and must be more
			 * than 256 bytes in size.
			 */
			if (end - p < 4)
				goto dodgy_cert;
			if (p[0] != 0x30 &&
			    p[1] != 0x82)
				goto dodgy_cert;
			plen = (p[2] << 8) | p[3];
			plen += 4;
			if (plen > end - p)
				goto dodgy_cert;

			key = key_create_or_update(make_key_ref(system_trusted_keyring, 1),
						   "asymmetric",
						   NULL,
						   p,
						   plen,
						   ((KEY_POS_ALL & ~KEY_POS_SETATTR) |
						   KEY_USR_VIEW | KEY_USR_READ),
						   KEY_ALLOC_NOT_IN_QUOTA |
						   KEY_ALLOC_TRUSTED);
			if (IS_ERR(key)) {
				pr_err("Problem loading in-kernel X.509 certificate (%ld)\n",
				       PTR_ERR(key));
			} else {
				set_bit(KEY_FLAG_BUILTIN, &key_ref_to_ptr(key)->flags);
				pr_notice("Loaded X.509 cert '%s'\n",
					  key_ref_to_ptr(key)->description);
				key_ref_put(key);
			}
			p += plen;
		}

		return 0;

	dodgy_cert:
		pr_err("Problem parsing in-kernel X.509 certificate list\n");
		return 0;
	}
	late_initcall(load_system_certificate_list);
```

#### 内核信任密匙是在编译的时候收集到的。收集 *.x509

#### kernel/Makefile
```
	obj-$(CONFIG_SYSTEM_TRUSTED_KEYRING) += system_keyring.o system_certificates.o
	...
	...
	
	###############################################################################
	#
	# Roll all the X.509 certificates that we can find together and pull them into
	# the kernel so that they get loaded into the system trusted keyring during
	# boot.
	#
	# We look in the source root and the build root for all files whose name ends
	# in ".x509".  Unfortunately, this will generate duplicate filenames, so we
	# have make canonicalise the pathnames and then sort them to discard the
	# duplicates.
	#
	###############################################################################
	ifeq ($(CONFIG_SYSTEM_TRUSTED_KEYRING),y)
	X509_CERTIFICATES-y := $(wildcard *.x509) $(wildcard $(srctree)/*.x509)
	X509_CERTIFICATES-$(CONFIG_MODULE_SIG) += signing_key.x509
	X509_CERTIFICATES := $(sort $(foreach CERT,$(X509_CERTIFICATES-y), \
				$(or $(realpath $(CERT)),$(CERT))))

	X509_TOOL_CERTIFICATES := $(wildcard $(srctree)/tool_certs/*.pub)

	ifeq ($(X509_CERTIFICATES),)
	$(warning *** No X.509 certificates found ***)
	endif

	ifneq ($(wildcard $(obj)/.x509.list),)
	ifneq ($(shell cat $(obj)/.x509.list),$(X509_CERTIFICATES))
	$(info X.509 certificate list changed)
	$(shell rm $(obj)/.x509.list)
	endif
	endif

	ifneq ($(wildcard $(obj)/.tool_x509.list),)
	ifneq ($(shell cat $(obj)/.tool_x509.list),$(X509_TOOL_CERTIFICATES))
	$(info X.509 tool_certificate list changed)
	$(shell rm $(obj)/.tool_x509.list)
	endif
	endif

	kernel/system_certificates.o: $(obj)/x509_certificate_list $(obj)/x509_tool_certificate_list

	quiet_cmd_x509certs  = CERTS   $@  
		cmd_x509certs  = cat $(X509_CERTIFICATES) /dev/null >$@ $(foreach X509,$(X509_CERTIFICATES),; echo "  - Including cert $(X509)")
	quiet_cmd_tool_x509certs  = CERTS   $@  
		cmd_tool_x509certs  = cat $(X509_TOOL_CERTIFICATES) /dev/null >$@ $(foreach X509,$(X509_TOOL_CERTIFICATES),; echo "  - Including cert $(X509)")

	targets += $(obj)/x509_certificate_list
	$(obj)/x509_certificate_list: $(X509_CERTIFICATES) $(obj)/.x509.list
		$(call if_changed,x509certs)

```

#### kernel/system_certificates.S
```
	#include <linux/export.h>
	#include <linux/init.h>

		__INITRODATA

		.align 8
		.globl VMLINUX_SYMBOL(system_certificate_list)
	VMLINUX_SYMBOL(system_certificate_list):
	__cert_list_start:
	#ifdef CONFIG_MODULE_SIG
	#ifdef CONFIG_MODULE_SIG_FORCE
		.incbin "kernel/x509_certificate_list"
	#endif
	#endif
	__cert_list_end:

		.align 8
		.globl VMLINUX_SYMBOL(system_certificate_list_size)
	VMLINUX_SYMBOL(system_certificate_list_size):
	#ifdef CONFIG_64BIT
		.quad __cert_list_end - __cert_list_start
	#else
		.long __cert_list_end - __cert_list_start
	#endif

		.align 8
		.globl VMLINUX_SYMBOL(tool_certificate_list)
	VMLINUX_SYMBOL(tool_certificate_list):
	__tool_cert_list_start:
	#ifdef CONFIG_MODULE_SIG
	#ifdef CONFIG_MODULE_SIG_FORCE
		.incbin "kernel/x509_tool_certificate_list"
	#endif
	#endif
	__tool_cert_list_end:

		.align 8
		.globl VMLINUX_SYMBOL(tool_certificate_list_size)
	VMLINUX_SYMBOL(tool_certificate_list_size):
	#ifdef CONFIG_64BIT
		.quad __tool_cert_list_end - __tool_cert_list_start
	#else
		.long __tool_cert_list_end - __tool_cert_list_start
	#endif
```

### 往内核插入自己密匙

kernel/system_keyring.c copy出来独立模块，将自己公匙导入系统
