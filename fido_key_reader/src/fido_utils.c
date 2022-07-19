#include <stddef.h>

#define KEY_ID_LEN 12
#define OTP_SECRET_BITS 48
#define OTP_COUNTER_BITS 16
#define OTP_TIMESTAMP_BITS 24
#define OTP_SESSION_BITS 8
#define OTP_RANDOM_PART_BITS 16
#define OTP_CHECKSUM_BITS 16

/**
 *
 * @param exp_hexBin_size: saves required number of bytes for hexadecimal string;
 * @param defsize: default size of single buffer;
 * @return: total required size/number of bytes for char arrays;
 */
int expected_mem_size(int *exp_hexBin_size, const int defsize){
	int size = KEY_ID_LEN + 1, hexbin_size;
	const int total_size = (OTP_SECRET_BITS + OTP_COUNTER_BITS + OTP_TIMESTAMP_BITS + OTP_SESSION_BITS + OTP_RANDOM_PART_BITS + OTP_CHECKSUM_BITS)/4;

	hexbin_size = defsize - KEY_ID_LEN + 1;
	if(hexbin_size <= total_size)
		hexbin_size = total_size + 1;

	if(exp_hexBin_size != NULL)
		*exp_hexBin_size = hexbin_size;

	size += hexbin_size*2;// for hex + for bin array;

	return size;
}

/**
 *
 * @return  {int}: the length of FIDO key ID;
 */
int get_key_ID_length(){
	return KEY_ID_LEN;
}

/**
 *	Returns pointer for OTP of FIDO response;
 *
 * @param fido_code: pointer to buffer of characters received from FIDO key;
 * @return: pointer to buffer where OTP starts;
 */
unsigned char *otp_pointer_pos(unsigned char *fido_code){
	return fido_code + KEY_ID_LEN;
}

/**
 *	Do the same what ordinary  strlen()  from  string.h  but also limit result by maxlen.
 *
 * @param str: string to be checked;
 * @param maxlen: max allowed length;
 * @return: limited length of string str;
 */
int strlen_max(unsigned char *str, const int maxlen){
	int i = 0;
	while(str[i] != '\0' && i < maxlen)
		i++;

	return i;
}

/**
 *	Converts modulo hexadecimal values in modhex[] into hexadecimal series in hex_output[];
 *
 * @param hex_output: output of function which has to have the same size as modhex;
 * @param modhex: list of modulo hexadecimal numbers to be converted;
 * @param len: length of both arrays;
 */
void mod_hex(unsigned char *hex_output, unsigned char *modhex, const int len){
	/*unsigned char mh[4][8] = {
		'0', 'c', '4', 'f', '8', 'j', 'c', 'r',
		'1', 'b', '5', 'g', '9', 'k', 'd', 't',
		'2', 'd', '6', 'h', 'a', 'l', 'e', 'u',
		'3', 'e', '7', 'i', 'b', 'n', 'f', 'v'
	};*/
	unsigned char mh_from[] = {'c', 'b', 'd', 'e', 'f', 'g', 'h', 'i', 'j', 'k', 'l', 'n', 'r', 't', 'u', 'v'},
	mh_to[] = {'0', '1', '2', '3', '4', '5', '6', '7', '8', '9', 'a', 'b', 'c', 'd', 'e', 'f'};
	int i, j;

	for(i = 0; i < len; i++){
		hex_output[i] = modhex[i];

		for(j = 0; j < 16; j++){
			if(modhex[i] == mh_from[j]){
				hex_output[i] = mh_to[j];
				break;
			}
		}
	}
	hex_output[len] = '\0';
}

/**
 *
 * @param bin: binary output of hex array, its length has to be min. half of len;
 * @param hex: input string as list of hexadecimal values;
 * @param len: length of hex[] array;
 */
void hex_to_bin_array(unsigned char* bin, unsigned char* hex, const int len){
	int i, j, move = 4;
	unsigned char hex_val;
	// bin[0] = bin[1] = '\0';

	for(i = j = 0; i < len; i++){
		hex_val = 0;
		if(hex[i] >= '0' && hex[i] <= '9')
			hex_val = hex[i] - '0';
		else if(hex[i] >= 'a' && hex[i] <= 'f')
			hex_val = hex[i] - 'a' + 10;

		if(move){
			bin[j] = hex_val << move;
			move=0;
		} else {
			bin[j++] |= hex_val;
			move=4;
		}
	}
}

/**
 *
 * @param fido_code: buffer of characters received from FIDO key;
 * @param hex: buffer for hexadecimal string of FIDO key response (OTP part);
 * @param bin: buffer/array of binary numbers for hex;
 * @param fido_code_len: length of fido_code;
 */
void key_code_to_hex_bin(unsigned char *fido_code, unsigned char *hex, unsigned char *bin, const int fido_code_len){
	unsigned char *otp = otp_pointer_pos(fido_code);
	const int hex_len = strlen_max(otp, fido_code_len - KEY_ID_LEN);

	mod_hex(hex, otp, hex_len);
	hex_to_bin_array(bin, hex, hex_len);

}

/**
 *	Moves KEY_ID_LEN bytes of characters from start of  fido_code  to  key_id  and overrides them with '.';
 *
 * @param key_id: buffer to export ID of key from fido_code (expected size is KEY_ID_LEN);
 * @param fido_code: buffer of characters received from FIDO key;
 * @param fido_code_len: length of fido_code;
 */
void copy_key_id_cover(unsigned char *key_id, unsigned char *fido_code, const int fido_code_len){
	int i;
	const int sizemin = (fido_code_len < KEY_ID_LEN) ? fido_code_len : KEY_ID_LEN;

	for(i = 0; i < sizemin; i++){
		key_id[i] = fido_code[i];
		fido_code[i] = '.';
	}
}

