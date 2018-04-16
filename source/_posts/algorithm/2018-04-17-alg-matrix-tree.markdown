---
layout: post
title: "生成树计数"
date: 2018-04-17 00:36:00 +0800
comments: false
categories:
- 2018
- 2018~04
- algorithm
- algorithm~base
tags:
---

http://blog.sina.com.cn/s/blog_a55522150102w6sp.html
http://www.xuebuyuan.com/1622347.html

### 基尔霍夫定理
算法思想:
```
	*(1)G的度数矩阵D[G]是一个n*n的矩阵,并且满足:当i≠j时,dij=0;当i=j时,dij等于vi的度数; 
	*(2)G的邻接矩阵A[G]是一个n*n的矩阵,并且满足:如果vi,vj之间有边直接相连,则aij=1,否则为0; 
	*定义图G的Kirchhoff矩阵C[G]为C[G]=D[G]-A[G]; 
	*Matrix-Tree定理:G的所有不同的生成树的个数等于其Kirchhoff矩阵C[G]任何一个n-1阶主子式的行列式的绝对值； 
	*所谓n-1阶主子式,就是对于r(1≤r≤n),将C[G]的第r行,第r列同时去掉后得到的新矩阵,用Cr[G]表示; 
	* 
	*Kirchhoff矩阵的特殊性质： 
	*(1)对于任何一个图G,它的Kirchhoff矩阵C的行列式总是0,这是因为C每行每列所有元素的和均为0; 
	*(2)如果G是不连通的,则它的Kirchhoff矩阵C的任一个主子式的行列式均为0; 
	*(3)如果G是一颗树,那么它的Kirchhoff矩阵C的任一个n-1阶主子式的行列式均为1; 
```

```
	#include <cmath>
	#include <cstdio>
	#include <cstdlib>
	#include <cstring>
	#include <iostream>
	#include <algorithm>
	#define  zero(x) (((x)>0?(x):(-x))<1e-15)
	using namespace std;
	const int MAXN = 110;
	double a[MAXN][MAXN], b[MAXN][MAXN];
	int G[MAXN][MAXN];
	int N, M;
	
	/*
	*生成树计数
	*1、G的度数矩阵D[G]是一个n*n的矩阵，并且满足：当i≠j时,dij=0；当i=j时，dij等于vi的度数。
	*2、G的邻接矩阵A[G]也是一个n*n的矩阵， 并且满足：如果vi、vj之间有边直接相连，则aij=1，否则为0。
	*我们定义G的Kirchhoff矩阵(也称为拉普拉斯算子)C[G]为C[G]=D[G]-A[G]，则Matrix-Tree定理可以描述为：
	*G的所有不同的生成树的个数等于其Kirchhoff矩阵C[G]任何一个n-1阶主子式的行列式的绝对值。
	*所谓n-1阶主子式，就是对于r(1≤r≤n)，将C[G]的第r行、第r列同时去掉后得到的新矩阵，用Cr[G]表示。
	*/
	
	double Det(double a[MAXN][MAXN], int n)
	{
		int i, j, k, sign = 0;
		double ret = 1, t;
		for(i = 0; i < n; ++i)
			for(j = 0; j < n; ++j)
				b[i][j] = a[i][j];
		for(i = 0; i < n; ++i)
		{
			if(zero(b[i][i]))
			{
				for(j = i + 1; j < n; ++j)
				{
					if(!zero(b[j][i]))
						break;
				}
				if(j == n)
					return 0;
				for(k = i; k < n; ++k)
					t = b[i][k], b[i][k] = b[j][k], b[j][k] = t;
				sign++;
			}
			ret *= b[i][i];
			for(k = i + 1; k < n; ++k)
				b[i][k] /= b[i][i];
			for(j = i + 1; j < n; ++j)
				for(k = i + 1; k < n; ++k)
					b[j][k] -= b[j][i] * b[i][k];
		}
		if(sign & 1)
			ret = - ret;
		return ret;
	}
	
	int main()
	{
		int T, u, v;
		scanf("%d", &T);
		while (T--)
		{
			scanf("%d %d", &N, &M);
			memset(G, 0, sizeof(G));
			memset(a, 0, sizeof(a));
			while(M--)
			{
				scanf("%d %d", &u, &v);
				G[u-1][v-1] = G[v-1][u-1] = 1;
			}
			for(int i = 0; i < N; ++i)
			{
			   int d = 0;
			   for (int j = 0; j < N; ++j) if(G[i][j]) d++;
			   a[i][i] = d;
			}
			for(int i = 0; i < N; ++i)
			{
				for (int j = 0; j < N; ++j)
				{
					if (G[i][j]) a[i][j] = -1;
				}
			}
		   double ans = Det(a, N - 1);
		   printf("%0.0lf\n", ans);
		}
		return 0;
	}
```

### SRM733-DIV1-B

https://community.topcoder.com/stat?c=round_stats&rd=17140&dn=1

#### Problem Statement
    
Consider an undirected, complete, simple graph G on n vertices. The vertices of G are labeled from 1 to n. Specifically, each pair of distinct vertices is connected by a single undirected edge, so there are precisely n*(n-1)/2 edges in this graph.
 
You are given a set E that contains some edges of the graph G. More precisely, you are given the vector <int>s x and y. For each valid i, (x[i], y[i]) is one of the edges in E.
 
A spanning tree of G is a subset of exactly n-1 edges of G such that the edges connect all n vertices. You may note that the edges of a spanning tree do indeed form a tree that is a subgraph of G and spans all its vertices.
 
We are interested in spanning trees that contain all of the edges in the provided set E. Compute and return the number of such spanning trees modulo 987,654,323. Two spanning trees are different if there is an edge of G that is in one of them but not in the other.
Definition
    
#### Class:
BuildingSpanningTreesDiv1

#### Method:
getNumberOfSpanningTrees

#### Parameters:
int, vector <int>, vector <int>

#### Returns:
int

#### Method signature:
int getNumberOfSpanningTrees(int n, vector <int> x, vector <int> y)

(be sure your method is public)

#### Limits
Time limit (s):
2.000

Memory limit (MB):
256

#### Notes  
987,654,323 is a prime number.

#### Constraints
n will be between 1 and 1,000, inclusive.  
x will contain between 1 and 1,000 elements, inclusive.  
y will contain between 1 and 1,000 elements, inclusive.  
x and y will contain the same number of elements.  
Each element of x will be between 1 and n-1, inclusive.  
Each element of y will be between 2 and n, inclusive.  
For each valid i, x[i] will be less than y[i].  
All edges in E will be distinct.  

#### Examples
0)  
3  
{1,2}  
{2,3}  
Returns: 1  
The edges in the provided set E alredy form a spanning tree, so there is exactly one spanning tree that contains them.

1)  
5  
{1,3,4}  
{2,4,5}  
Returns: 6  
There are six ways to add the one missing edge: one endpoint of the new edge must lie in the set {1,2} and the other in the set {3,4,5}.

2)  
4  
{1}  
{2}  
Returns: 8  
There are eight spanning trees that contain the edge (1, 2):  
{(1, 2), (1, 3), (1, 4)}  
{(1, 2), (1, 3), (2, 4)}  
{(1, 2), (1, 3), (3, 4)}  
{(1, 2), (2, 3), (2, 4)}  
{(1, 2), (1, 4), (2, 3)}  
{(1, 2), (1, 4), (3, 4)}  
{(1, 2), (2, 3), (3, 4)}  
{(1, 2), (2, 4), (3, 4)}  

3)  
4  
{1,2,1}  
{2,3,3}  
Returns: 0  
The set E contains a cycle, and thus there is no spanning tree that contains all these edges.  

4)  
8  
{1,4,2,3,5}  
{4,7,6,5,8}  
Returns: 144  

5)  
1000  
{1}  
{2}  
Returns: 529013784  

Don't forget to take the modulo.

This problem statement is the exclusive and proprietary property of TopCoder, Inc. Any unauthorized use or reproduction of this information without the prior written consent of TopCoder, Inc. is strictly prohibited. (c)2003, TopCoder, Inc. All rights reserved.

```
	#include <algorithm>
	#include <cassert>
	#include <cmath>
	#include <cstdio>
	#include <cstdlib>
	#include <cstring>
	#include <ctime>
	#include <iostream>
	#include <map>
	#include <queue>
	#include <set>
	#include <sstream>
	#include <stdint.h>
	#include <string>
	#include <utility>
	#include <vector>
	 
	using namespace std;
	 
	int const MAX_N = 1003;
	int const MOD = 987654323;
	 
	int p [MAX_N];
	int s [MAX_N];
	 
	int root (int v)
	{
		if (p[v] != v)
		{
			p[v] = root (p[v]);
		}
		return p[v];
	}
	 
	bool unite (int u, int v)
	{
		u = root (u);
		v = root (v);
		if (u == v)
		{
			return false;
		}
		p[v] = u;
		s[u] += s[v];
		return true;
	}
	 
	int powmod (int a, int b)
	{
		int res = 1;
		for ( ; b > 0; b >>= 1)
		{
			if (b & 1)
			{
				res = (res * 1LL * a) % MOD;
			}
			a = (a * 1LL * a) % MOD;
		}
		return res;
	}
	 
	int a [MAX_N] [MAX_N];
	int n;
	 
	int det (void)
	{
		int res = 1;
		for (int i = 1; i < n; i++)
		{
			int j;
			for (j = i; j < n; j++)
			{
				if (a[j][i])
				{
					break;
				}
			}
			if (j == n)
			{
				return 0;
			}
			if (j != i)
			{
				for (int k = i; k < n; k++)
				{
					swap (a[i][k], a[j][k]);
				}
			}
			res = (res * 1LL * a[i][i]) % MOD;
			int inv = powmod (a[i][i], MOD - 2);
			for (int j = i + 1; j < n; j++)
			{
				int mult = (a[j][i] * 1LL * inv) % MOD;
				for (int k = i; k < n; k++)
				{
					a[j][k] = (a[j][k] -
							a[i][k] * 1LL * mult) % MOD;
				}
			}
		}
		return (res + MOD) % MOD;
	}
	 
	class BuildingSpanningTreesDiv1
	{
	public:
		int getNumberOfSpanningTrees (int n, vector <int> x, vector <int> y)
		{
			for (int i = 0; i < n; i++)
			{
				p[i] = i;
				s[i] = 1;
			}
			int k = (int) (x.size ());
			for (int w = 0; w < k; w++)
			{
				int u = x[w] - 1;
				int v = y[w] - 1;
				if (!unite (u, v))
				{
					return 0;
				}
				a[u][v] += 1;
				a[v][u] += 1;
				a[u][u] -= 1;
				a[v][v] -= 1;
			}
	 
			int t = 0;
			for (int i = 0; i < n; i++)
			{
				if (p[i] == i)
				{
					s[t] = s[i];
					t += 1;
				}
			}
	 
			n -= k;
			::n = n;
	 
			memset (a, 0, sizeof (a));
			for (int i = 0; i < n; i++)
			{
				for (int j = i + 1; j < n; j++)
				{
					int e = s[i] * s[j];
					a[i][j] -= e;
					a[j][i] -= e;
					a[i][i] = (a[i][i] + e) % MOD;
					a[j][j] = (a[j][j] + e) % MOD;
				}
			}
	 
			int res = det ();
			return res;
		}
	};
```

