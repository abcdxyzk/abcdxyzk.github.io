---
layout: post
title: "centos7.4 上使用 SQL Server 2019"
date: 2020-11-11 18:17:00 +0800
comments: false
categories:
- 2020
- 2020~11
- tools
- tools~sqlservel
tags:
---

https://docs.microsoft.com/zh-cn/sql/linux/quickstart-install-connect-red-hat?view=sql-server-ver15

## 安装 SQL Server

https://packages.microsoft.com/rhel/7/mssql-server-2019/

https://packages.microsoft.com/rhel/7/mssql-server-2019/mssql-server-15.0.4073.23-4.x86_64.rpm

#### 下载 Microsoft SQL Server 2019 Red Hat 存储库配置文件：

```
	sudo curl -o /etc/yum.repos.d/mssql-server.repo https://packages.microsoft.com/config/rhel/7/mssql-server-2019.repo
```

#### 运行以下命令以安装 SQL Server：

```
	sudo yum install -y mssql-server
```

#### 包安装完成后，运行 mssql-conf setup，按照提示设置 SA 密码并选择版本。

```
	sudo /opt/mssql/bin/mssql-conf setup
```

请确保为 SA 帐户指定强密码（最少 8 个字符，包括大写和小写字母、十进制数字和/或非字母数字符号）。

#### 完成配置后，验证服务是否正在运行：

```
	systemctl status mssql-server
```

## 安装 SQL Server 命令行工具

若要创建数据库，则需要使用可在 SQL Server 上运行 Transact-SQL 语句的工具进行连接。 以下步骤将安装 SQL Server 命令行工具：sqlcmd 和 bcp。

#### 下载 Microsoft Red Hat 存储库配置文件。

```
	sudo curl -o /etc/yum.repos.d/msprod.repo https://packages.microsoft.com/config/rhel/7/prod.repo
```

#### 如果安装了早期版本的 mssql-tools，请删除所有旧的 unixODBC 包。

```
	sudo yum remove unixODBC-utf16 unixODBC-utf16-devel
```

#### 运行以下命令，以使用 unixODBC 开发人员包安装 mssql-tools。 有关详细信息，请参阅安装 Microsoft ODBC Driver for SQL Server (Linux)。

```
	sudo yum install -y mssql-tools unixODBC-devel
```

为方便起见，向 PATH 环境变量添加 /opt/mssql-tools/bin/。 这样可以在不指定完整路径的情况下运行这些工具。 运行以下命令以修改登录会话和交互式/非登录会话的路径：

```
	echo 'export PATH="$PATH:/opt/mssql-tools/bin"' >> ~/.bash_profile
	echo 'export PATH="$PATH:/opt/mssql-tools/bin"' >> ~/.bashrc
	source ~/.bashrc
```

## 本地连接

以下步骤使用 sqlcmd 本地连接到新的 SQL Server 实例。

#### 使用 SQL Server 名称 (-S)，用户名 (-U) 和密码 (-P) 的参数运行 sqlcmd 。 在本教程中，用户进行本地连接，因此服务器名称为 localhost。 用户名为 SA，密码是在安装过程中为 SA 帐户提供的密码。

```
	sqlcmd -S localhost -U SA -P '<YourPassword>'
```

可以在命令行上省略密码，以收到密码输入提示。

如果以后决定进行远程连接，请指定 -S 参数的计算机名称或 IP 地址，并确保防火墙上的端口 1433 已打开。

如果成功，应会显示 sqlcmd 命令提示符：1>。


## 创建和查询数据

下面各部分将逐步介绍如何使用 sqlcmd 新建数据库、添加数据并运行简单查询。

#### 新建数据库

以下步骤创建一个名为 TestDB 的新数据库。

在 sqlcmd 命令提示符中，粘贴以下 Transact-SQL 命令以创建测试数据库：

```
	CREATE DATABASE TestDB
```

在下一行中，编写一个查询以返回服务器上所有数据库的名称：

```
	SELECT Name from sys.Databases
```

前两个命令没有立即执行。 必须在新行中键入 GO 才能执行以前的命令：
```
	GO
```

提示

若要详细了解如何编写 Transact-SQL 语句和查询，请参阅教程：编写 Transact-SQL 语句。

#### 插入数据

接下来创建一个新表 Inventory，然后插入两个新行。

在 sqlcmd 命令提示符中，将上下文切换到新的 TestDB 数据库：

```
	USE TestDB
```

创建名为 Inventory 的新表：

```
	CREATE TABLE Inventory (id INT, name NVARCHAR(50), quantity INT)
```

将数据插入新表：

```
	INSERT INTO Inventory VALUES (1, 'banana', 150); INSERT INTO Inventory VALUES (2, 'orange', 154);
```

要执行上述命令的类型 GO：

```
	GO
```

#### 选择数据

现在，运行查询以从 Inventory 表返回数据。

通过 sqlcmd 命令提示符输入查询，以返回 Inventory 表中数量大于 152 的行：

```
	SELECT * FROM Inventory WHERE quantity > 152;
```

执行此命令：

```
	GO
```

#### 退出 sqlcmd 命令提示符

要结束 sqlcmd 会话，请键入 QUIT：
```
	QUIT
```


## sqlcmd

```
	sqlcmd -U SA  -P password -Q“SQL语句”  执行完退出
```

#### 备份语句

备份数据库名为test
```
	sqlcmd -U SA  -P password -Q“BACKUP DATABASE test TO  DISK='/tmp/test.bak' with format ,init”
```

#### 使用sqlcmd 命令还原

例如还原数据库test

```
	sqlcmd -U SA -P password  -Q "RESTORE DATABASE  test FROM  DISK='/tmp/sqlserver/test.bak' WITH REPLACE"
```

#### master 数据库特殊，需要在单用户模式下备份

方法停止sqlserver数据库，已单用户模式启动数据库

```
	/opt/mssql/bin/sqlservr  -m"SQLCMD"   
```
然后在执行还原命令即可

