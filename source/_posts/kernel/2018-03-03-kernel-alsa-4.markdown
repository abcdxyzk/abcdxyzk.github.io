---
layout: post
title: "截获alsa-pcm音频"
date: 2018-03-03 19:25:00 +0800
comments: false
categories:
- 2018
- 2018~03
- kernel
- kernel~sound
tags:
---

http://blog.csdn.net/killmice/article/details/51777205

https://www.linuxidc.com/Linux/2014-11/109948.htm

http://www.ituring.com.cn/article/201363

#### avconv 命令录制

arecord -l

avconv -f alsa -i hw:0 output.wav

--------------------

#### 截获 alsa 框架中 pcm 缓冲区

sound/core/pcm_native.c
```
	const struct file_operations snd_pcm_f_ops[2] = {
		{
			.owner =                THIS_MODULE,
			.write =                snd_pcm_write,
			.aio_write =            snd_pcm_aio_write,
			.open =                 snd_pcm_playback_open,
			.release =              snd_pcm_release,
			.llseek =               no_llseek,
			.poll =                 snd_pcm_playback_poll,
			.unlocked_ioctl =       snd_pcm_playback_ioctl,
			.compat_ioctl =         snd_pcm_ioctl_compat,
			.mmap =                 snd_pcm_mmap,
			.fasync =               snd_pcm_fasync,
			.get_unmapped_area =    snd_pcm_get_unmapped_area,
		},   
		{
			.owner =                THIS_MODULE,
			.read =                 snd_pcm_read,
			.aio_read =             snd_pcm_aio_read,
			.open =                 snd_pcm_capture_open,
			.release =              snd_pcm_release,
			.llseek =               no_llseek,
			.poll =                 snd_pcm_capture_poll,
			.unlocked_ioctl =       snd_pcm_capture_ioctl,
			.compat_ioctl =         snd_pcm_ioctl_compat,
			.mmap =                 snd_pcm_mmap,
			.fasync =               snd_pcm_fasync,
			.get_unmapped_area =    snd_pcm_get_unmapped_area,
		}
	};
```

在进入 snd_pcm_playback_ioctl 时 cmd=SNDRV_PCM_IOCTL_HWSYNC 时 copy 出 runtime->dma_area 对应数据

```
	struct snd_pcm_substream *substream = (struct snd_pcm_substream *)regs->si;
	struct snd_pcm_runtime *runtime = substream->runtime;
	struct snd_pcm_mmap_status *status = runtime->status;
	struct snd_pcm_mmap_control *control = runtime->control;

	if (substream_now == substream) {
		unsigned int i, bytes = runtime->dma_bytes;
		unsigned char *ch = runtime->dma_area;

		/*
		printk("ioctl1: size=%d hw_ptr=%d appl_ptr=%d avail_min=%d audio_tstamp=%d\n",
			(int)runtime->dma_bytes, (int)status->hw_ptr, (int)control->appl_ptr,
			(int)control->avail_min, (int)status->audio_tstamp.tv_sec);
		*/

		if (status->hw_ptr > hw_ptr) {
			hw_ptr = status->hw_ptr;
			hw_ptr_err ++; 
		}

		for (i = hw_ptr * 4; before(i, control->appl_ptr * 4) && len < LEN; i ++, len ++)
			dest[len] = ch[i % bytes];

		hw_ptr = control->appl_ptr;
	}

```
