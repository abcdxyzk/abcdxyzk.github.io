---
layout: post
title: "wav音频文件格式"
date: 2018-03-03 17:10:00 +0800
comments: false
categories:
- 2018
- 2018~03
- kernel
- kernel~sound
tags:
---


```
	# mp3 转 wav
	ffmpeg -i source.mp3 output.wav

	# wav 转 mp3
	ffmpeg -i source.wav output.mp3

	# 从视频中提取音频
	ffmpeg -i source.mp4 -vn output.wav
```

http://blog.csdn.net/mcgrady_tracy/article/details/52502263

http://blog.csdn.net/u013286409/article/details/47414273

wav是微软开发的一种音频文件格式，注意，wav文件格式是无损音频文件格式，相对于其他音频格式文件数据是没有经过压缩的，通常文件也相对比较大些。

文件格式如图所示：

![](/images/kernel/2018-03-03-1.gif)


解析代码如下：
```
	#include <stdio.h>
	#include <stdint.h>
	#include <stdlib.h>

	struct WAV_Format {
		uint32_t ChunkID;	/* "RIFF" */
		uint32_t ChunkSize;	/* 36 + Subchunk2Size */
		uint32_t Format;	/* "WAVE" */

		/* sub-chunk "fmt" */
		uint32_t Subchunk1ID;	/* "fmt " */
		uint32_t Subchunk1Size;	/* 16 for PCM */
		uint16_t AudioFormat;	/* PCM = 1*/
		uint16_t NumChannels;	/* Mono = 1, Stereo = 2, etc. */
		uint32_t SampleRate;	/* 8000, 44100, etc. */
		uint32_t ByteRate;	/* = SampleRate * NumChannels * BitsPerSample/8 */
		uint16_t BlockAlign;	/* = NumChannels * BitsPerSample/8 */
		uint16_t BitsPerSample;	/* 8bits, 16bits, etc. */
		// LIST 包含歌手歌名专辑等

		/* sub-chunk "data" */
		uint32_t Subchunk2ID;   /* "data" */
		uint32_t Subchunk2Size; /* data size */
	};

	int main(void)
	{
		FILE *fp = NULL;
		struct WAV_Format wav;

		fp = fopen("test.wav", "rb");
		if (!fp) {
			printf("can't open audio file\n");
			exit(1);
		}

		fread(&wav, 1, sizeof(struct WAV_Format), fp);

		printf("ChunkID \t%x\n", wav.ChunkID);
		printf("ChunkSize \t%d\n", wav.ChunkSize);
		printf("Format \t\t%x\n", wav.Format);
		printf("Subchunk1ID \t%x\n", wav.Subchunk1ID);
		printf("Subchunk1Size \t%d\n", wav.Subchunk1Size);
		printf("AudioFormat \t%d\n", wav.AudioFormat);
		printf("NumChannels \t%d\n", wav.NumChannels);
		printf("SampleRate \t%d\n", wav.SampleRate);
		printf("ByteRate \t%d\n", wav.ByteRate);
		printf("BlockAlign \t%d\n", wav.BlockAlign);
		printf("BitsPerSample \t%d\n", wav.BitsPerSample);
		printf("Subchunk2ID \t%x\n", wav.Subchunk2ID);
		printf("Subchunk2Size \t%d\n", wav.Subchunk2Size);

		fclose(fp);

		return 0;
	}
```

#### wav概述

WAV为微软公司（Microsoft)开发的一种声音文件格式，它符合RIFF(Resource Interchange File Format)文件规范，用于保存Windows平台的音频信息资源，被Windows平台及其应用程序所广泛支持，该格式也支持MSADPCM，CCITT A LAW等多种压缩运算法，支持多种音频数字，取样频率和声道，标准格式化的WAV文件和CD格式一样，也是44.1K的取样频率，16位量化数字，因此在声音文件质量和CD相差无几！ WAV打开工具是WINDOWS的媒体播放器。

通常使用三个参数来表示声音，量化位数，取样频率和采样点振幅。量化位数分为8位，16位，24位三种，声道有单声道和立体声之分，单声道振幅数据为n*1矩阵点，立体声为n*2矩阵点，取样频率一般有11025Hz(11kHz) ，22050Hz(22kHz)和44100Hz(44kHz) 三种，不过尽管音质出色，但在压缩后的文件体积过大！相对其他音频格式而言是一个缺点，其文件大小的计算方式为：WAV格式文件所占容量（B) = （取样频率 X量化位数X 声道） X 时间 / 8 (字节= 8bit) 每一分钟WAV格式的音频文件的大小为10MB，其大小不随音量大小及清晰度的变化而变化。

#### RIFF文件

1. 简介RIFF全称为资源互换文件格式（ResourcesInterchange FileFormat），RIFF文件是windows环境下大部分多媒体文件遵循的一种文件结构,RIFF文件所包含的数据类型由该文件的扩展名来标识，能以RIFF文件存储的数据包括：音频视频交错格式数据（.AVI) 波形格式数据（.WAV) 位图格式数据（.RDI) MIDI格式数据（.RMI)调色板格式（.PAL)多媒体电影（.RMN)动画光标（.ANI)其它RIFF文件（.BND)

2. CHUNK
chunk是组成RIFF文件的基本单元，它的基本结构如下：
```
	struct chunk {
		u32 id;		/* 块标志 */
		u32 size;	/* 块大小 */
		u8 dat[size];	/* 块内容 */
	};
```
id 由4个ASCII字符组成，用以识别块中所包含的数据。如：'RIFF','LIST','fmt','data','WAV','AVI'等等，由于这种文件结构最初是由Microsoft和IBM为PC机所定义,RIFF文件是按照little-endian[2] 字节顺序写入的。

size（块大小） 是存储在data域中数据的长度,id与size域的大小则不包括在该值内。

dat（块内容） 中所包含的数据是以字(WORD)为单位排列的，如果该数据结构长度是奇数，则在最后添加一个空(NULL)字节。

#### chunk块中有且仅有两种类型块：'RIFF'和'LIST'类型可以包含其他块，而其它块仅能含有数据。

'RIFF'和'LIST'类型的chunk结构如下
```
	struct chunk {
		u32 id;		/* 块标志 */
		u32 size;	/* 块大小 */
		/*此时的dat = type + restdat */
		u32 type;	/* 类型 */
		u8 restdat[size]; /* dat中除type4个字节后剩余的数据*/
	};
```

可以看出，'RIFF'和'LIST'也是chunk,只是它的dat由两部分组成type和restdat。

type,由4个ASCII字符组成，代表RIFF文件的类型，如'WAV','AVI '；或者'LIST'块的类型，如avi文件中的列表'hdrl','movi'。

restdat,dat中除type4个字节后剩余的数据，包括块内容，包含若干chunk和'LIST'

2.1 FOURCC 一个FOURCC(fourcharacter code）是一个占4个字节的数据，一般表示4个ASCII字符。在RIFF文件格式中，FOURCC非常普遍,structchunk 中的id成员，'LIST','RIFF'的type成员，起始标识等信息都是用FOURCC表示的。FOURCC一般是四个字符，如'abcd'这样的形式，也可以三个字符包含一个空格，如'abc'这样的形式。

RIFF文件的FileData部分由若干个'LIST'和chunk组成，而'LIST'的ListData又可以由若干个'LIST'和chunk组成，即'LIST'是可以嵌套的。
'RIFF',FileType,'LIST',ListType,ChunkID都是FOURCC,即使用4字节的ASIIC字符标识类型。

FileSize,ListSize,ChunkSize为little-endian32-bit正整数，表示Type（只有'RIFF','LIST'chunk有Type)+Data一起的大小，注意它是little-endian表示，如：0x00123456,存储地址由低到高，在little-endian系统中的存储表示为0x56341200（字节由低位到高位存储），而在big-endian为0x00123456（字节由高位到低位存储）。32bit整数0x00123456存储地址低--------->；高little-endian（字节由低位到高位存储）56341200big-endian（字节由高位到低位存储）00123456

