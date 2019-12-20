---
layout: post
title: "NDS示例"
date: 2019-12-20 41:05:00 +0800
comments: false
categories:
- 2019
- 2019~12
- tools
- tools~dns
tags:
---

### DNS 示例

```
	// gcc dns.c -lpthread
	
	#include <stdio.h>	//printf
	#include <string.h>	//strlen
	#include <stdlib.h>	//malloc
	#include <sys/socket.h>	//you know what this is for
	#include <arpa/inet.h>	//inet_addr, inet_ntoa, ntohs etc
	#include <netinet/in.h>
	#include <unistd.h>	//getpid
	#include <pthread.h>
	#include <time.h>
	
	#define T_A	1	//IPv4 address
	#define T_NS	2	//Nameserver
	#define T_CNAME	5	// canonical name
	#define T_SOA	6	/* start of authority zone */
	#define T_PTR	12	/* domain name pointer */
	#define T_MX	15	//Mail server
	#define T_AAAA	28	// IPv6
	
	#define NIPQUAD(addr) ((unsigned char *)&addr)[0], ((unsigned char *)&addr)[1], ((unsigned char *)&addr)[2], ((unsigned char *)&addr)[3]
	
	//DNS header structure
	struct DNS_HEADER
	{
		unsigned short id;	// identification number
	
		unsigned char rd :1;	// recursion desired
		unsigned char tc :1;	// truncated message
		unsigned char aa :1;	// authoritive answer
		unsigned char opcode :4;	// purpose of message
		unsigned char qr :1;	// query/response flag
	
		unsigned char rcode :4;	// response code
		unsigned char cd :1;	// checking disabled
		unsigned char ad :1;	// authenticated data
		unsigned char z :1;	// its z! reserved
		unsigned char ra :1;	// recursion available
	
		unsigned short q_count;	// number of question entries
		unsigned short ans_count;	// number of answer entries
		unsigned short auth_count;	// number of authority entries
		unsigned short add_count; // number of resource entries
	};
	
	//Constant sized fields of query structure
	struct QUESTION
	{
		unsigned short qtype;
		unsigned short qclass;
	};
	
	//Constant sized fields of the resource record structure
	#pragma pack(push, 1)
	struct R_DATA
	{
		unsigned short type;
		unsigned short _class;
		unsigned int ttl;
		unsigned short data_len;
	};
	#pragma pack(pop)
	
	//Pointers to resource record contents
	struct RES_RECORD
	{
		unsigned char *name;
		struct R_DATA *resource;
		unsigned char *rdata;
	};
	
	//Structure of a Query
	typedef struct
	{
		unsigned char *name;
		struct QUESTION *ques;
	} QUERY;
	
	// convert www.google.com to 3www6google3com
	void ChangetoDnsNameFormat(unsigned char* dns, unsigned char* host)
	{
		int lock = 0, i;
		for (i = 0; i <= strlen(host); i ++) {
			if (host[i] == '.' || host[i] == '\0') {
				*dns++ = i - lock;
				for( ; lock < i; lock ++)
					*dns++ = host[lock];
				lock ++;
			}
		}
		*dns++ = '\0';
	}
	
	// convert 3www6google3com0 to www.google.com
	void changeToHost(unsigned char *dns)
	{
		int i = 0, j = 0, p;
	
		while (i < 100 && dns[i] && dns[i] < 100) {
			p = dns[i];
			i = i + p + 1;
			while (p --) {
				dns[j] = dns[j+1];
				j ++;
			}
			dns[j++] = '.';
		}
		dns[j-1] = '\0'; //remove the last dot
	}
	
	int readName(unsigned char *reader, unsigned char *buffer, unsigned char *to)
	{
		unsigned int p = 0, step = 1, offset, count = 0;
		int i, j;
	
		//read the names in 3www6google3com format
	
		while (*reader != 0) {
			if (*reader >= 0xc0) {
				offset = (*reader)*256 + *(reader+1) - 0xc000; //49152 = 11000000 00000000
				reader = buffer + offset;
				step = 0;
			} else {
				to[p++] = *reader ++;
				count += step;
			}
		}
		to[p] = '\0';
		count += (step == 0) ? 2 : 1;
	
		changeToHost(to);
	
		return count;
	}
	
	/*
	 * sending a packet
	 */
	void sendPacket(int fd, struct sockaddr_in *dest, unsigned char *host, int query_type)
	{
		unsigned char buf[65536], *qname, *reader;
		int i, j;
	
		struct DNS_HEADER *dns = NULL;
		struct QUESTION *qinfo = NULL;
	
		//Set the DNS structure to standard queries
		dns = (struct DNS_HEADER *)&buf;
	
		dns->id = htons(getpid());
		dns->qr = 0; //This is a query
		dns->opcode = 0; //This is a standard query
		dns->aa = 0; //Not Authoritative
		dns->tc = 0; //This message is not truncated
		dns->rd = 1; //Recursion Desired
		dns->ra = 0; //Recursion not available! hey we dont have it (lol)
		dns->z = 0;
		dns->ad = 0;
		dns->cd = 0;
		dns->rcode = 0;
		dns->q_count = htons(1); //we have only 1 question
		dns->ans_count = 0;
		dns->auth_count = 0;
		dns->add_count = 0;
	
		//point to the query portion
		qname = &buf[sizeof(struct DNS_HEADER)];
	
		ChangetoDnsNameFormat(qname, host);
		qinfo = (struct QUESTION*)&buf[sizeof(struct DNS_HEADER) + (strlen(qname) + 1)]; //fill it
	
		qinfo->qtype = htons(query_type); //type of the query, A, MX, CNAME, NS etc
		qinfo->qclass = htons(1); //its internet (lol)
	
		if (sendto(fd, buf, sizeof(struct DNS_HEADER) + (strlen(qname) + 1) + sizeof(struct QUESTION), 0, (struct sockaddr*)dest, sizeof(*dest)) < 0) {
			perror("sendto failed");
		}
		printf("send Done\n");
		return;
	}
	
	void *recvPacket(void *arg)
	{
		int fd = *((int *)arg);
	
		unsigned char buf[65536], *qname, *reader;
		int i, j;
	
		struct sockaddr_in a, dest;
	
		struct DNS_HEADER *dns = NULL;
		struct QUESTION *qinfo = NULL;
		struct R_DATA *resource;
	
		char name[256];
		char rdata[256];
	
		while (1) {
			//Receive the answer
			i = sizeof(dest);
			if (recvfrom(fd, buf, 65536, 0, (struct sockaddr*)&dest, (socklen_t*)&i) < 0) {
				perror("recvfrom failed");
			}
			printf("recv Done\n");
		
			dns = (struct DNS_HEADER*) buf;
		
			printf("The response contains:\n");
			printf("%d Questions.\n", ntohs(dns->q_count));
			printf("%d Answers.\n", ntohs(dns->ans_count));
			printf("%d Authoritative Servers.\n", ntohs(dns->auth_count));
			printf("%d Additional records.\n\n", ntohs(dns->add_count));
		
			//move ahead of the dns header and the query field
			//reader = &buf[sizeof(struct DNS_HEADER) + (strlen((const char*)qname) + 1) + sizeof(struct QUESTION)];
			reader = &buf[sizeof(struct DNS_HEADER)];
	
			//Start reading answers
			printf("Questions Records: %d\n", ntohs(dns->q_count));
			for (i = 0; i < ntohs(dns->q_count); i ++) {
				reader += readName(reader, buf, name);
				qinfo = (struct QUESTION *)reader;
				reader = reader + sizeof(struct QUESTION);
		
				printf("Name: %s Type: %d\n", name, ntohs(qinfo->qtype));
			}
			printf("\n");
		
			printf("Answer Records: %d\n", ntohs(dns->ans_count));
			for (i = 0; i < ntohs(dns->ans_count); i++) {
				reader += readName(reader, buf, name);
				resource = (struct R_DATA*)(reader);
				reader = reader + sizeof(struct R_DATA);
		
				printf("Name: %s Type: %d ", name, ntohs(resource->type));
		
				if (ntohs(resource->type) == T_A || ntohs(resource->type) == T_AAAA) { //if its an ipv4 address
					printf("IPv4: %d.%d.%d.%d", NIPQUAD(reader));
					reader = reader + ntohs(resource->data_len);
				} else {
					reader += readName(reader, buf, rdata);
					if (ntohs(resource->type) == T_CNAME)
						printf("CNAME: %s", rdata);
				}
				printf("\n");
			}
			printf("\n");
		
			//read authorities
			printf("Authoritive Records: %d\n", ntohs(dns->auth_count));
			for(i = 0; i < ntohs(dns->auth_count); i++) {
				reader += readName(reader, buf, name);
				resource = (struct R_DATA*)(reader);
				reader += sizeof(struct R_DATA);
		
				reader += readName(reader, buf, rdata);
		
				printf("Name: %s Type: %d ", name, ntohs(resource->type));
		
				if (ntohs(resource->type) == T_NS) {
					printf("nameserver: %s", rdata);
				}
				printf("\n");
			}
			printf("\n");
		
			//read additional
			printf("Additional Records: %d\n", ntohs(dns->add_count));
			for(i = 0; i < ntohs(dns->add_count); i++) {
				reader += readName(reader, buf, name);
				resource = (struct R_DATA*)(reader);
				reader += sizeof(struct R_DATA);
		
				printf("Name: %s Type: %d ", name, ntohs(resource->type));
		
				if (ntohs(resource->type) == T_A || ntohs(resource->type) == T_AAAA) {
					printf("IPv4: %d.%d.%d.%d", NIPQUAD(reader));
					reader = reader + ntohs(resource->data_len);
				} else {
					reader += readName(reader, buf, rdata);
				}
				printf("\n");
			}
			printf("\n\n");
		}
	}
	
	/*
	 * Get the DNS servers from /etc/resolv.conf file on Linux
	 */
	void get_dns_servers(char dns_servers[])
	{
		FILE *fp;
		char line[200], *p;
		if ((fp = fopen("/etc/resolv.conf", "r")) == NULL) {
			printf("Failed opening /etc/resolv.conf file \n");
		}
	
		while (fgets(line, 200, fp)) {
			if (line[0] == '#') {
				continue;
			}
			if (strncmp(line, "nameserver", 10) == 0) {
				p = strtok(line, " ");
				p = strtok(NULL, " ");
	
				//p now is the dns ip :)
				//????
			}
		}
	
		strcpy(dns_servers, "127.0.1.1");
	}
	
	int main(int argc, char *argv[])
	{
		int fd;
		struct sockaddr_in dest;
		unsigned char hostname[100];
		pthread_t tid;
	
		char dns_servers[100];
	
		//Get the DNS servers from the resolv.conf file
		get_dns_servers(dns_servers);
	
		dest.sin_family = AF_INET;
		dest.sin_port = htons(53);
		dest.sin_addr.s_addr = inet_addr(dns_servers);
	
		fd = socket(AF_INET, SOCK_DGRAM, IPPROTO_UDP);
	
		if (pthread_create(&tid, NULL, recvPacket, (void *)&fd)) {
			printf("pthread err\n");
			exit(-1);
		}
	
		while (1) {
			printf("Enter Hostname to Lookup: ");
			scanf("%s", hostname);
			sendPacket(fd, &dest, hostname, T_A);
			usleep(500000);
		}
	
		pthread_join(tid, NULL);
		return 0;
	}
```

