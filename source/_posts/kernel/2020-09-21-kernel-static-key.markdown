---
layout: post
title: "static_key 机制"
date: 2020-09-21 11:46:00 +0800
comments: false
categories:
- 2020
- 2020~09
- kernel
- kernel~base
tags:
---

https://www.dazhuanlan.com/2019/10/10/5d9f4b6a20f82/

简单来说，如果你对代码性能很敏感，而且大多数情况下分支路径是确定的，可以考虑使用static keys。static keys可以代替普通的变量进行分支判断，目的是用来优化频繁使用if-else判断的问题，这里涉及到指令分支预取的一下问题。简单地说，现代cpu都有预测功能，变量的判断有可能会造成硬件预测失败，影响流水线性能。虽然有likely和unlikely，但还是会有小概率的预测失败。

#### 定义一个static_key

```
	struct static_key key = STATIC_KEY_INIT_FALSE;
```

注意：这个key及其初始值必须是静态存在的，不能定义为局部变量或者使用动态分配的内存。通常为全局变量或者静态变量。 其中的STATIC_KEY_INIT_FALSE表示这个key的默认值为false，对应的分支默认不进入，如果是需要默认进入的，用STATIC_KEY_INIT_TRUE，这里如果不赋值，系统默认为STATIC_KEY_INIT_FALSE，在代码运行中不能再用STATIC_KEY_INIT_FALSE/STATIC_KEY_INIT_TRUE进行赋值。
判断语句

对于默认为false（STATIC_KEY_INIT_FALSE）的，使用

```
	if (static_key_false(&key))
		do unlikely code
	else
		do likely code
```

对于默认为true（STATIC_KEY_INIT_TRUE）的，使用

```
	if (static_key_true((&static_key)))
		do the likely work;
	else
		do unlikely work
```

#### 修改判断条件

使用static_key_slow_inc让分支条件变成true，使用static_key_slow_dec让分支条件变成false，与其初始的默认值无关。该接口是带计数的， 也就是：

  初始值为STATIC_KEY_INIT_FALSE的，那么： static_key_slow_inc; static_key_slow_inc; static_key_slow_dec 那么 if (static_key_false((&static_key)))对应的分支会进入，而再次static_key_slow_dec后，该分支就不再进入了。

  初始值为STATIC_KEY_INIT_TRUE的，那么： static_key_slow_dec; static_key_slow_dec; static_key_slow_inc 那么 if (static_key_true((&static_key)))对应的分支不会进入，而再次static_key_slow_inc后，该分支就进入了。


#### static-key的内核实现

static_key_false的实现：

对X86场景其实现如下，其它架构下的实现类似。

```
	static __always_inline bool static_key_false(struct static_key *key)
	{
		return arch_static_branch(key);
	}

	static __always_inline bool arch_static_branch(struct static_key *key)
	{
		asm_volatile_goto("1:"
			".byte " __stringify(STATIC_KEY_INIT_NOP) "nt"
			".pushsection __jump_table,  "aw" nt"
			_ASM_ALIGN "nt"
			_ASM_PTR "1b, %l[l_yes], %c0 nt"
			".popsection nt"
			: :  "i" (key) : : l_yes);
		return false;
	l_yes:
		return true;
	}
```

  其中的asm_volatile_goto宏 使用了asm goto，是gcc的特性，其允许在嵌入式汇编中jump到一个C语言的label，详见gcc的manual(https://gcc.gnu.org/onlinedocs/gcc/Extended-Asm.html)， 但是本处其作用只是将C语言的label “l_yes”传递到嵌入式汇编中。

STATIC_KEY_INITIAL_NOP其实就是NOP指令

`.pushsection __jump_table` 是通知编译器，以下的内容写入到段 `__jump_table`

`_ASM_PTR “1b, %l[l_yes], %c0` ，是往段`__jump_table`中写入label "1b"、C label "l_yes"和输入参数`struct static_key *key`的地址，这些信息对应于struct jump_entry 中的code、target、key成员，在后续的处理中非常重要。

.popsection表示以下的内容回到之前的段，其实多半就是.text段。

可见，以上代码的作用就是：执行NOP指令后返回false，同时把NOP指令的地址、代码"return true"对应地址、`struct static_key *key`的地址写入到段`__jump_table`。由于固定返回为false且为always inline，编译器会把

```
	if (static_key_false((&static_key)))
		do the unlikely work;
	else
		do likely work
```

优化为：

```
	nop
	do likely work
	retq
	l_yes:
	do the unlikely work;
```

正常场景，就没有判断了。

static_key_true的实现：

```
	static __always_inline bool static_key_true(struct static_key *key)
	{
		return !static_key_false(key);
	}
```

执行static_key_slow_inc(&key)后，底层通过gcc提供的goto功能，再结合c代码编写的动态修改内存功能，就可以让使用key的代码从执行false分支变成执行true分支。当然这个更改代价时比较昂贵的，不是所有的情况都适用。

