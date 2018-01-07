---
layout: post
title: "Linux模式设计4-数据对齐"
date: 2018-01-08 01:33:00 +0800
comments: false
categories:
- 2018
- 2018~01
- kernel
- kernel~base
tags:
---

http://blog.chinaunix.net/uid-20608849-id-3027967.html

内核在某些应用中，为了实现某种机制，比如分页，或者提高访问效率需要保证数据或者指针地址对齐到某个特定的整数值，比如连接代码脚本。这个值必须是2N。数据对齐，可以看做向上圆整的一种运算。

```
	include/linux/kernel.h
	#define ALIGN(x, a) __ALIGN_MASK(x, (typeof(x))(a) - 1)
	#define __ALIGN_MASK(x, mask) (((x) + (mask))&~(mask))
	#define PTR_ALIGN(p, a) ((typeof(p))ALIGN((unsigned long)(p), (a)))
	#define IS_ALIGNED(x, a) (((x) & ((typeof(x))(a) - 1)) == 0)
```

内核提供了两个用来对齐的宏ALIGN和PTR_ALIGN，一个实现数据对齐，而另一个实现指针的对齐。它们实现的核心都是`__ALIGN_MASK`，其中mask参数为低N位全为1，其余位全为0的掩码，它从圆整目标值2N - 1得到。`__ALIGN_MASK`得到对齐值，对于数据来说直接返回即可，而对于指针则需要进行强制转换。IS_ALIGNED宏用来判断当前值是否对齐与指定的值。内核中的分页对齐宏定义如下：

```
	arch/arm/include/asm/page.h
	/* PAGE_SHIFT determines the page size */
	#define PAGE_SHIFT 12
	#define PAGE_SIZE (1UL << PAGE_SHIFT)

	include/linux/mm.h
	/* to align the pointer to the (next) page boundary */
	#define PAGE_ALIGN(addr) ALIGN(addr, PAGE_SIZE)
```

PAGE_SIZE定义在体系架构相关的代码中，通常为4K。内核中提供的特性功能的对齐宏均是对ALIGN的扩展。下面提供一个代码示例，并给出结果：

```
	#include <stdio.h>
	......
	int main()
	{
		int a = 0 ,i = 0;
		int *p = &a;

		for(; i < 6; i++)
		 printf("ALIGN(%d, 4): %x\n", i, ALIGN(i, 4));
		
		printf("p:%p, PTR_ALIGN(p, 8): %p\n", p, PTR_ALIGN(p, 8));
		printf("IS_ALIGNED(7, 8): %d, IS_ALIGNED(16, 8): %d\n", IS_ALIGNED(7, 8), IS_ALIGNED(16, 8));
		
		return 0;
	}
```

对齐宏测试结果：

```
	ALIGN(0, 4): 0
	ALIGN(1, 4): 4
	......
	ALIGN(4, 4): 4
	ALIGN(5, 4): 8
	p:0xbf96c01c, PTR_ALIGN(p, 8): 0xbf96c020
	IS_ALIGNED(7, 8): 0, IS_ALIGNED(16, 8): 1
```

