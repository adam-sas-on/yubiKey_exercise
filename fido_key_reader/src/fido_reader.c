#include <stdio.h>
#include <stdlib.h>
#include <sys/time.h>
#include <sys/types.h>
#include <termios.h>
#include <unistd.h>
#include "../include/fido_utils.h"

#define BUF_SIZE 64

/**
 *	Sets immediate standard input without printing;
 *
 * @param term: structure of parameters for terminal IO;
 */
void makeRawInput(struct termios *term){
	term->c_iflag &= ~(BRKINT | ICRNL | ISTRIP | IXON
                       | INLCR | IGNCR | IGNBRK | PARMRK);
	term->c_oflag &= ~OPOST;// this includes things like expanding tabs to spaces;
	term->c_lflag &= ~(ECHO | ECHONL | ICANON | ISIG | IEXTEN);
	term->c_cflag &= ~(CSIZE | PARENB);
	term->c_cflag |= CS8;
}

/**
 *	Reads standard input char by char until ENTER is pressed or buf is filled up
 * without printing on standard output;
 *
 * @param buf: buffer for pressed characters;
 * @param bufsize: size of buffer in bytes;
 */
void read_FIDO_key(unsigned char *buf, const int bufsize){
	struct timeval wait_time;
	fd_set fdst;
	int c = 0, pressed, buf_index = 0;
	const int stdin_fno = fileno(stdin);

	for(c = 0; c < bufsize; c++)
		buf[c] = '\0';

	c = buf_index = 0;
	while(buf_index < bufsize && c != '\n' && c != 13){
		wait_time.tv_sec = 2;
		FD_ZERO(&fdst);
		FD_SET(stdin_fno, &fdst);
		pressed = select(stdin_fno+1, &fdst, NULL, NULL, &wait_time);

		switch(pressed){
			case -1:
				break;
			case 0:// wait_time  seconds passed;
				break;
			default:
				c = fgetc(stdin);
				buf[buf_index++] = (c == 13) ? '\0' : (unsigned char)c;
		}
	}
	buf[bufsize - 1] = '\0';
}

/**
 *	Prints on screen (standard output) results of reading FIDO key;
 *
 * @param fido_code: buffer of characters received from FIDO key;
 * @param key_id: buffer for ID of FIDO key (expected size is KEY_ID_LEN);
 * @param hex: buffer for hexadecimal string of FIDO key response (expected size is BUF_SIZE - KEY_ID_LEN);
 * @param bin: buffer/array of binary numbers for hex;
 */
void present_FIDO_key(unsigned char *fido_code, unsigned char *key_id, unsigned char *hex, unsigned char *bin){
	fd_set fdst_rd;
	int c, i, j, n;

	copy_key_id_cover(key_id, fido_code, BUF_SIZE);
	key_code_to_hex_bin(fido_code, hex, bin, BUF_SIZE);

	printf("Key response:  %s\n\r", fido_code);
	printf("                     Hex:  %s\n\r", hex);

	printf("Press '\x1B[1;37mY\x1B[0m' to display key ID or another button to cancel ");
	fflush(stdout);

	FD_ZERO(&fdst_rd);
	FD_SET(fileno(stdin), &fdst_rd);
	n = select(fileno(stdin)+1, &fdst_rd, NULL, NULL, NULL);

	c = (n != -1 && n != 0)? fgetc(stdin) : 0;
	if(c == 'Y'){
		printf("\r  Key Id: %s                              \n\r", key_id);
	} else
		printf("\r                                                          \n\r");

	printf("  Binary form of OTP of key response:                      \n\r");
	fflush(stdout);


	for(n = 0; hex[n] != '\0'; n++)
		;

	i = n/2;
	n = (n&1) ? i+1 : i;

	for(i = 0; i < n; i++){
		for(j = 7; j >= 0; j--)
			putchar( ( bin[i]&(1<<j) )?'1':'0' );
		putchar(' ');
	}

	puts("\n\r");
	fflush(stdout);
}

/**
 *
 */
void run(){
	struct timeval wait_time;
	fd_set fdst;
	struct termios newTerm, oldTerm;
	unsigned char *mem = NULL, *fido_chars, *hex, *bin, *key_id;
	int c = 0, pressed, size;
	char run, questions_not_printed = 1;
	const int stdin_fno = fileno(stdin);

	size = expected_mem_size(&c, BUF_SIZE + 1);
	mem = (unsigned char*)calloc(size + BUF_SIZE + 1, sizeof(unsigned char));
	if(mem == NULL){
		printf("Could not alloc required memory!");
		return;
	}
	fido_chars = mem;
	hex = mem + BUF_SIZE + 1;
	bin = hex + c;
	key_id = bin + c;


	tcgetattr(stdin_fno, &oldTerm);
	tcgetattr(stdin_fno, &newTerm);

	makeRawInput(&newTerm);
	tcsetattr(stdin_fno, TCSANOW, &newTerm);

	run = 1;
	wait_time.tv_usec = 0;

	while(run){
		if(questions_not_printed){
			printf("\tSelect task\n\r1) press '1' for reading FIDO key\n\rQ) press 'Q' for exit ");
			fflush(stdout);
			questions_not_printed = 0;
		}

		wait_time.tv_sec = 2;
		FD_ZERO(&fdst);
		FD_SET(stdin_fno, &fdst);
		pressed = select(stdin_fno+1, &fdst, NULL, NULL, &wait_time);

		switch(pressed){
			case -1:
				break;
			case 0:// wait_time  seconds passed;
				break;
			default:
				c = fgetc(stdin);
				if(c == 'Q')
					run = 0;
				else if(c == '1'){
					printf("\n\r\tReading FIDO key, touch it to start...\n\r");
					fflush(stdout);
					questions_not_printed = 1;

					read_FIDO_key(fido_chars, BUF_SIZE);

					present_FIDO_key(fido_chars, key_id, hex, bin);
				}
		}
	}

	printf("\n\r");

	tcsetattr(stdin_fno, TCSANOW, &oldTerm);
	free((void*)mem);
}

