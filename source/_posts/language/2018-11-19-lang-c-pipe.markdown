---
layout: post
title: "进程通信--pipe管道"
date: 2018-11-19 02:45:00 +0800
comments: false
categories:
- 2018
- 2018~11
- language
- language~c
tags:
---

#### Linux函数原型

```
	#include <unistd.h>

	int pipe(int filedes[2]);
```

filedes[0]用于读出数据，读取时必须关闭写入端，即close(filedes[1]);

filedes[1]用于写入数据，写入时必须关闭读取端，即close(filedes[0])。

#### 程序实例：

```
	#include <stdio.h>
	#include <unistd.h>

	#define MAXLINE	20
	int main(void)
	{
		int n;
		int fd[2];
		pid_t pid;
		char line[MAXLINE];

		if (pipe(fd) < 0) {				/* 先建立管道得到一对文件描述符 */
			return -1;
		}

		if ((pid = fork()) < 0)				/* 父进程把文件描述符复制给子进程 */
			return -2;
		else if(pid > 0) {				/* 父进程写 */
			close(fd[0]);				/* 关闭读描述符 */
			write(fd[1], "\nhello world\n", 14);
		} else {							/* 子进程读 */
			close(fd[1]);				/* 关闭写端 */
			n = read(fd[0], line, MAXLINE);
			write(STDOUT_FILENO, line, n);
		}

		return 0;
	}
```
