#include <stdio.h>
#include <stdarg.h>
#include <stdlib.h>
#include <string.h>
#include <assert.h>
#include "../include/fido_utils.h"

#define BUF_SIZE 64

/**
 *	Fill array by arguments listed as last params of fill_arr_by_arguments();
 *
 * @param arr: array to be filled;
 * @param count_args: number of arguments to fill in;
 */
void fill_arr_by_arguments(unsigned char *arr, const int count_args, ...){
	va_list args;
	int i;
	unsigned char val;

	va_start(args, count_args);
	for(i = 0; i < count_args; i++){
		val = (unsigned char)va_arg(args, int);
		arr[i] = val;
	}

	va_end(args);
}

/**
 *	Compare two arrays by checking if first length values are the same;
 *
 * @param arr1: array to check;
 * @param arr2: array to check;
 * @param length: length of both arrays to compare;
 * @return  {int}: returns 1 when elements of arrays are equal, 0 otherwise;
 */
int assert_array_equals(unsigned char *arr1, unsigned char *arr2, const int length){
	int i;
	for(i = 0; i < length; i++){
		if(arr1[i] != arr2[i])
			return 0;
	}
	return 1;
}

/**
 *	Test expected_mem_size() to check the risk of "segmentation fault"
 * for FIDO key length according to fido chars;
 *
 * @param fido: example of input key to get basic size;
 */
void test_buffer_size(char *fido){
	int bs, expected_min_for_hex, expected_min_for_bin, exp_min_size, size, hexbin_size;
	const int fido_code_len = strlen((char*)fido), key_id_len = get_key_ID_length();

	expected_min_for_hex = fido_code_len - key_id_len;
	expected_min_for_bin = expected_min_for_hex/2;
	if( (expected_min_for_hex&1) )
		expected_min_for_bin++;

	expected_min_for_hex++;// for '\0';
	expected_min_for_bin++;// for '\0';
	exp_min_size = expected_min_for_hex + expected_min_for_bin + key_id_len + 1;

	for(bs = 1; bs <= fido_code_len + 2; bs++){
		size = expected_mem_size(&hexbin_size, bs);

		if(hexbin_size < expected_min_for_hex || size < exp_min_size){
			fprintf(stderr, "Bad size for mem! (\"base size\" = %d; \"hexBin size\" = %d, expected: %d; total size = %d, expected: %d)\n", bs, hexbin_size, expected_min_for_hex, size, exp_min_size);
		}
		assert(hexbin_size >= expected_min_for_hex && size >= exp_min_size);// error when: hexbin_size < expected_min_for_hex || size < exp_min_size;
		printf("Size for mem \x1B[32m[OK]\x1B[0m (\"base size\" = %d, \"hexBin size\" = %d, total size = %d)\n", bs, hexbin_size, size);
	}

}

/**
 *
 */
void test_case(unsigned char *fido, unsigned char *expected_key_id,
                                    unsigned char *expected_hex,
                                    unsigned char *expected_bin,
                                    unsigned char *key_id,
                                    unsigned char *hex,
                                    unsigned char *bin){
	int assert_eq, length;

	printf("\tCase: %s\n", fido);
	fflush(stdout);
	copy_key_id_cover(key_id, fido, BUF_SIZE);
	key_code_to_hex_bin(fido, hex, bin, BUF_SIZE);

	length = strlen((char*)expected_key_id);
	assert_eq = assert_array_equals(key_id, expected_key_id, length);
	if(!assert_eq || length == 0){
		fprintf(stderr, "Key ID does not equal! (%s != %s)\n", key_id, expected_key_id);
	}
	assert(assert_eq != 0 && length > 0);
	printf("Key ID \x1B[32m[OK]\x1B[0m (length = %d)\n", length);


	length = strlen((char*)expected_hex);
	assert_eq = assert_array_equals(hex, expected_hex, length);
	if(!assert_eq){
		fprintf(stderr, "Hex does not equal! (%s != %s)\n", hex, expected_hex);
	}
	assert(assert_eq != 0);
	printf("Hex \x1B[32m[OK]\x1B[0m (length = %d)\n", length);


	length = 16;
	assert_eq = assert_array_equals(bin, expected_bin, length);
	if(!assert_eq){
		fprintf(stderr, "Bin does not equal! (%s != %s)\n", bin, expected_bin);
	}
	assert(assert_eq != 0);
	printf("Bin \x1B[32m[OK]\x1B[0m (length = %d)\n", length);
}

/**
 *
 */
void run_tests(){
	unsigned char *mem = NULL, *fido_chars, *hex, *bin, *key_id;
	unsigned char *expected_hex, *expected_bin, *expected_key_id;
	int c, size;


	size = expected_mem_size(&c, BUF_SIZE + 1);
	mem = (unsigned char*)calloc(size*2 + BUF_SIZE + 1, sizeof(unsigned char));
	if(mem == NULL){
		printf("Could not alloc required memory!");
		return;
	}
	fido_chars = mem;
	hex = mem + BUF_SIZE + 1;
	bin = hex + c;
	key_id = bin + c;

	expected_hex = hex + size;
	expected_bin = expected_hex + c;
	expected_key_id = expected_bin + c;

	strcpy((char*)fido_chars, "cccccgrrgncrjkghkekltrvcirclbuvgdiivlcdfjitn");
	strcpy((char*)expected_key_id, "cccccgrrgncr");
	strcpy((char*)expected_hex, "8956939adcf07c0a1ef5277fa02487db");
	fill_arr_by_arguments(expected_bin, 16, 0x89, 0x56, 0x93, 0x9A, 0xdc, 0xf0, 0x7c, 0x0a, 0x1e, 0xf5, 0x27, 0x7f, 0xa0, 0x24, 0x87, 0xdb);
	//memcpy(expected_bin, (unsigned char*){0x89, 0x56, 0x93, 0x9a, 0xdc, 0xf0, 0x7c, 0x0a, 0x1e, 0xf5, 0x27, 0x7f, 0xa0, 0x24, 0x87, 0xdb}, 16);

	test_case(fido_chars, expected_key_id, expected_hex, expected_bin, key_id, hex, bin);
	puts("");


	strcpy((char*)fido_chars, "cccccgrrgncrbvfilnlhbidjhuiljrrbkbecbbtkucvj");
	strcpy((char*)expected_key_id, "cccccgrrgncr");
	strcpy((char*)expected_hex, "1f47aba617286e7a8cc1913011d9e0f8");
	fill_arr_by_arguments(expected_bin, 16, 0x1f, 0x47, 0xab, 0xa6, 0x17, 0x28, 0x6e, 0x7a, 0x8c, 0xc1, 0x91, 0x30, 0x11, 0xd9, 0xe0, 0xf8);

	test_case(fido_chars, expected_key_id, expected_hex, expected_bin, key_id, hex, bin);
	puts("");


	strcpy((char*)fido_chars, "ccchcvrrgncrvfljhncirrunfggkhthirlgfbkbruftv");
	strcpy((char*)expected_key_id, "ccchcvrrgncr");
	strcpy((char*)expected_hex, "f4a86b07cceb45596d67ca54191ce4df");
	fill_arr_by_arguments(expected_bin, 16, 0xf4, 0xa8, 0x6b, 0x07, 0xcc, 0xeb, 0x45, 0x59, 0x6d, 0x67, 0xca, 0x54, 0x19, 0x1c, 0xe4, 0xdf);

	test_case(fido_chars, expected_key_id, expected_hex, expected_bin, key_id, hex, bin);
	puts("");


	strcpy((char*)fido_chars, "ccchcvrrgncrttjduejjcbfighhevrebrbttcifdfchn");
	strcpy((char*)expected_key_id, "ccchcvrrgncr");
	strcpy((char*)expected_hex, "dd82e38801475663fc31c1dd0742406b");
	fill_arr_by_arguments(expected_bin, 16, 0b11011101, 0b10000010, 0b11100011, 0b10001000, 0b00000001, 0b01000111, 0b01010110, 0b01100011, 0b11111100, 0b00110001, 0b11000001, 0b11011101, 0b00000111, 0b01000010, 0x40, 0x6b);

	test_case(fido_chars, expected_key_id, expected_hex, expected_bin, key_id, hex, bin);
	puts("");

	test_buffer_size("ccchcvrrgncrttjduejjcbfighhevrebrbttcifdfchn");
	puts("");

	free((void*)mem);
}

