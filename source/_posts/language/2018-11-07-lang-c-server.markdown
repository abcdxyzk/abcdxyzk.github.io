---
layout: post
title: "select,poll,epoll"
date: 2018-11-07 23:13:00 +0800
comments: false
categories:
- 2018
- 2018~11
- language
- language~c
tags:
---

样例 [socket_select_poll_epoll.tar](/download/tools/socket_select_poll_epoll.tar)

-----------------------


https://blog.csdn.net/jyy305/article/details/73012706


## select

#### select函数预备知识

1.struct fd_set可以理解为一个集合，这个集合中存放的是文件描述符(file descriptor)，即文件句柄，这可以是我们所说的普通意义的文件，当然Unix下任何设备、管道、FIFO等都是文件形式，全部包括在内，所以毫无疑问一个socket就是一个文件，socket句柄就是一个文件描述符。fd_set集合可以通过一些宏由人为来操作。

(1) FD_CLR(inr fd,fd_set* set)：用来清除描述词组set中相关fd 的位  
(2) FD_ISSET(int fd,fd_set *set）：用来测试描述词组set中相关fd 的位是否为真  
    FD_SET（int fd,fd_set*set）：用来设置描述词组set中相关fd的位  

2.struct timeval是一个大家常用的结构，用来代表时间值，有两个成员，一个是秒数，另一个是毫秒数。  

FD_ZERO（fd_set *set）；用来清除描述词组set的全部位


#### select函数介绍

```
	int select(int maxfdp,fd_set *readfds,fd_set *writefds,fd_set *errorfds,struct timeval *timeout);
```

maxfdp : 需要监视的最大文件描述符加1。

readfds、writefds、errorfds：分别对应于需要检测的可读文件描述符的集合，可写文件描述符的集 合及异常文件描述符的集合。

timeout：等待时间，这个时间内，需要监视的描述符没有事件  
发⽣生则函数返回，返回值为0。设为-1表示阻塞式等待，一直等到有事件就绪，函数才会返回，0表示非阻塞式等待，没有事件就立即返回，大于0表示等待的时间。  
返回值：大于0表示就绪时间的个数，等于0表示timeout等待时间到了，小于0表示调用失败。

#### select函数原理

select系统调用是用来让我们的程序监视多个文件句柄的状态变化的。程序会停在select这⾥里等待，直到被监视的文件句柄有一个或多个发⽣生了状态改变。关于文件句柄，其实就是⼀一个整数，我们最熟悉的句柄是0、1、2三个，0是标准输入，1是标准输出，2是标准错误输出。0、1、2是整数表示的，对应的FILE *结构的表示就是stdin、stdout、stderr。

1.我们通常需要额外定义一个数组来保存需要监视的文件描述符，并将其他没有保存描述符的位置初始化为一个特定值，一般为-1，这样方便我们遍历数组，判断对应的文件描述符是否发生了相应的事件。

2.采用上述的宏操作FD_SET（int fd,fd_set*set）遍历数组将关心的文件描述符设置到对应的事件集合里。并且每次调用之前都需要遍历数组，设置文件描述符。

3.调用select函数等待所关心的文件描述符。有文件描述符上的事件就绪后select函数返回，没有事件就绪的文件描述符在文件描述符集合中对应的位置会被置为0，这就是上述第二步的原因。

4.select 返回值大于0表示就绪的文件描述符的个数，0表示等待时间到了，小于0表示调用失败，因此我们可以遍历数组采用FD_ISSET(int fd,fd_set *set）判断哪个文件描述符上的事件就绪，然后执行相应的操作。


---------------

https://www.cnblogs.com/Anker/p/3261006.html

## poll

#### 1、基本知识

poll的机制与select类似，与select在本质上没有多大差别，管理多个描述符也是进行轮询，根据描述符的状态进行处理，但是poll没有最大文件描述符数量的限制。poll和select同样存在一个缺点就是，包含大量文件描述符的数组被整体复制于用户态和内核的地址空间之间，而不论这些文件描述符是否就绪，它的开销随着文件描述符数量的增加而线性增大。

#### 2、poll函数

函数格式如下所示：

```
	#include <poll.h>
	int poll ( struct pollfd * fds, unsigned int nfds, int timeout);
```
pollfd 结构体定义如下：

```
	struct pollfd {
		int fd;         /* 文件描述符 */
		short events;   /* 等待的事件 */
		short revents;  /* 实际发生了的事件 */
	} ; 
```

每一个pollfd结构体指定了一个被监视的文件描述符，可以传递多个结构体，指示poll()监视多个文件描述符。每个结构体的events域是监视该文件描述符的事件掩码，由用户来设置这个域。revents域是文件描述符的操作结果事件掩码，内核在调用返回时设置这个域。events域中请求的任何事件都可能在revents域中返回。合法的事件如下：

```
	POLLIN           有数据可读。
	POLLRDNORM       有普通数据可读。
	POLLRDBAND       有优先数据可读。
	POLLPRI          有紧迫数据可读。
	POLLOUT          写数据不会导致阻塞。
	POLLWRNORM       写普通数据不会导致阻塞。
	POLLWRBAND       写优先数据不会导致阻塞。
	POLLMSGSIGPOLL   消息可用。

	# 此外，revents域中还可能返回下列事件：
	# 这些事件在events域中无意义，因为它们在合适的时候总是会从revents中返回。
	POLLER           指定的文件描述符发生错误。
	POLLHUP          指定的文件描述符挂起事件。
	POLLNVAL         指定的文件描述符非法。
```

使用poll()和select()不一样，你不需要显式地请求异常情况报告。

POLLIN | POLLPRI等价于select()的读事件，POLLOUT |POLLWRBAND等价于select()的写事件。POLLIN等价于POLLRDNORM |POLLRDBAND，而POLLOUT则等价于POLLWRNORM。

例如，要同时监视一个文件描述符是否可读和可写，我们可以设置 events为POLLIN | POLLOUT。在poll返回时，我们可以检查revents中的标志，对应于文件描述符请求的events结构体。如果POLLIN事件被设置，则文件描述符可以被读取而不阻塞。如果POLLOUT被设置，则文件描述符可以写入而不导致阻塞。这些标志并不是互斥的：它们可能被同时设置，表示这个文件描述符的读取和写入操作都会正常返回而不阻塞。

timeout参数指定等待的毫秒数，无论I/O是否准备好，poll都会返回。timeout指定为负数值表示无限超时，使poll()一直挂起直到一个指定事件发生；timeout为0指示poll调用立即返回并列出准备好I/O的文件描述符，但并不等待其它的事件。这种情况下，poll()就像它的名字那样，一旦选举出来，立即返回。


##### 返回值和错误代码
成功时，poll()返回结构体中revents域不为0的文件描述符个数；如果在超时前没有任何事件发生，poll()返回0；失败时，poll()返回-1，并设置errno为下列值之一：

```
	EBADF       一个或多个结构体中指定的文件描述符无效。
	EFAULTfds   指针指向的地址超出进程的地址空间。
	EINTR       请求的事件之前产生一个信号，调用可以重新发起。
	EINVALnfds  参数超出PLIMIT_NOFILE值。
	ENOMEM      可用内存不足，无法完成请求。
```


-----------------------------

https://www.jianshu.com/p/ee381d365a29

https://blog.csdn.net/libaineu2004/article/details/70197825

https://www.cnblogs.com/fnlingnzb-learner/p/5835573.html

## epoll

在linux的网络编程中，很长的时间都在使用select来做事件触发。在linux新的内核中，有了一种替换它的机制，就是epoll。
相比于select，epoll最大的好处在于它不会随着监听fd数目的增长而降低效率。因为在内核中的select实现中，它是采用轮询来处理的，轮询的fd数目越多，自然耗时越多。并且，在linux/posix_types.h头文件有这样的声明：
```
	#define __FD_SETSIZE    1024
```

表示select最多同时监听1024个fd，当然，可以通过修改头文件再重编译内核来扩大这个数目，但这似乎并不治本。

EPOLL 的API用来执行类似poll()的任务。能够用于检测在多个文件描述符中任何IO可用的情况。Epoll API可以用于边缘触发(edge-triggered)和水平触发(level-triggered), 同时epoll可以检测更多的文件描述符。以下的系统调用函数提供了创建和管理epoll实例：

epoll_create() 可以创建一个epoll实例并返回相应的文件描述符(epoll_create1() 扩展了epoll_create() 的功能)。  
注册相关的文件描述符使用epoll_ctl()  
epoll_wait() 可以用于等待IO事件。如果当前没有可用的事件，这个函数会阻塞调用线程。  


### 关于ET、LT两种工作模式：
ET模式仅当状态发生变化的时候才获得通知,这里所谓的状态的变化并不包括缓冲区中还有未处理的数据,也就是说,如果要采用ET模式,需要一直read/write直到出错为止,很多人反映为什么采用ET模式只接收了一部分数据就再也得不到通知了,大多因为这样;而LT模式是只要有数据没有处理就会一直通知下去的.

下面情况下推荐使用ET模式:

使用非阻塞的IO。  
epoll_wait() 只需要在read或者write返回的时候。

相比之下，当我们使用LT的时候（默认）,epoll会比poll更简单更快速，而且我们可以使用在任何一个地方。


### API介绍

#### 1. 创建epoll
```
	#include <sys/epoll.h>

	int epoll_create(int size);
	int epoll_create1(int flags);
```

epoll_create() 可以创建一个epoll实例。在linux 内核版本大于2.6.8 后，这个size 参数就被弃用了，但是传入的值必须大于0。

在 epoll_create () 的最初实现版本时， size参数的作用是创建epoll实例时候告诉内核需要使用多少个文件描述符。内核会使用 size 的大小去申请对应的内存(如果在使用的时候超过了给定的size， 内核会申请更多的空间)。现在，这个size参数不再使用了（内核会动态的申请需要的内存）。但要注意的是，这个size必须要大于0，为了兼容旧版的linux 内核的代码。

epoll_create() 会返回新的epoll对象的文件描述符。这个文件描述符用于后续的epoll操作。如果不需要使用这个描述符，请使用close关闭。

epoll_create1() 如果flags的值是0，epoll_create1()等同于epoll_create()除了过时的size被遗弃了。当然flasg可以使用 EPOLL_CLOEXEC，请查看 open() 中的O_CLOEXEC来查看 EPOLL_CLOEXEC有什么用。

##### 返回值: 如果执行成功，返回一个非负数(实际为文件描述符), 如果执行失败，会返回-1，具体原因请查看erro


#### 2. 设置epoll事件
```
	#include <sys/epoll.h>

	int epoll_ctl(int epfd, int op, int fd, struct epoll_event *event);
```

epoll的事件注册函数，它不同与select()是在监听事件时告诉内核要监听什么类型的事件，而是在这里先注册要监听的事件类型。第一个参数是epoll_create()的返回值，第二个参数表示动作，用三个宏来表示：

```
	EPOLL_CTL_ADD	注册新的fd到epfd中；
	EPOLL_CTL_MOD	修改已经注册的fd的监听事件；
	EPOLL_CTL_DEL	从epfd中删除一个fd；
```
第三个参数是需要监听的fd，第四个参数是告诉内核需要监听什么事，struct epoll_event结构如下：

```
	typedef union epoll_data {
		void *ptr;
		int fd;
		__uint32_t u32;
		__uint64_t u64;
	} epoll_data_t;

	struct epoll_event {
		__uint32_t events; /* Epoll events */
		epoll_data_t data; /* User data variable */
	};
```

events可以是以下几个宏的集合：
```
	EPOLLIN		表示对应的文件描述符可以读（包括对端SOCKET正常关闭）；
	EPOLLOUT	表示对应的文件描述符可以写；
	EPOLLPRI	表示对应的文件描述符有紧急的数据可读（这里应该表示有带外数据到来）；
	EPOLLERR	表示对应的文件描述符发生错误；
	EPOLLHUP	表示对应的文件描述符被挂断；
	EPOLLET		将EPOLL设为边缘触发(Edge Triggered)模式，这是相对于水平触发(Level Triggered)来说的。
	EPOLLONESHOT	只监听一次事件，当监听完这次事件之后，如果还需要继续监听这个socket的话，需要再次把这个socket加入到EPOLL队列里
```

##### 返回值：如果成功，返回0。如果失败，会返回-1， errno将会被设置

有以下几种错误：
```
	EBADF  - epfd 或者 fd 是无效的文件描述符。
	EEXIST - op是EPOLL_CTL_ADD，同时 fd 在之前，已经被注册到epoll中了。
	EINVAL - epfd不是一个epoll描述符。或者fd和epfd相同，或者op参数非法。
	ENOENT - op是EPOLL_CTL_MOD或者EPOLL_CTL_DEL，但是fd还没有被注册到epoll上。
	ENOMEM - 内存不足。
	EPERM  - 目标的fd不支持epoll。
```

#### 3. 等待epoll事件
```
	#include <sys/epoll.h>

	int epoll_wait(int epfd, struct epoll_event * events, int maxevents, int timeout);
```

epoll_wait 这个系统调用是用来等待epfd中的事件。events指向调用者可以使用的事件的内存区域。maxevents告知内核有多少个events，必须要大于0.

timeout这个参数是用来制定epoll_wait 会阻塞多少毫秒，会一直阻塞到下面几种情况：

一个文件描述符触发了事件。  
被一个信号处理函数打断，或者timeout超时。

当timeout等于-1的时候这个函数会无限期的阻塞下去，当timeout等于0的时候，就算没有任何事件，也会立刻返回。

##### epoll_pwait
还有一个系统调用epoll_pwait ()。epoll_pwait()和epoll_wait ()的关系就像select()和 pselect()的关系。和pselect()一样，epoll_pwait()可以让应用程序安全的等待知道某一个文件描述符就绪或者捕捉到信号。

下面的 epoll_pwait () 调用：
```
	ready = epoll_pwait(epfd, &events, maxevents, timeout, &sigmask);
```
在内部等同于:
```
	pthread_sigmask(SIG_SETMASK, &sigmask, &origmask);
	ready = epoll_wait(epfd, &events, maxevents, timeout);
	pthread_sigmask(SIG_SETMASK, &origmask, NULL);
```

如果 sigmask为NULL, epoll_pwait()等同于epoll_wait()。

返回值：有多少个IO事件已经准备就绪。如果返回0说明没有IO事件就绪，而是timeout超时。遇到错误的时候，会返回-1，并设置 errno。

有以下几种错误:
```
	EBADF  - epfd是无效的文件描述符
	EFAULT - 指针events指向的内存没有访问权限
	EINTR  - 这个调用被信号打断。
	EINVAL - epfd不是一个epoll的文件描述符，或者maxevents小于等于0
```

