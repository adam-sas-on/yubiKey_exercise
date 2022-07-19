#ifndef FIDO_UTILS
#define FIDO_UTILS

int expected_mem_size(int *exp_hexBin_size, const int defsize);
int get_key_ID_length();

void key_code_to_hex_bin(unsigned char *fido_code, unsigned char *hex, unsigned char *bin, const int fido_code_len);
void copy_key_id_cover(unsigned char *key_id, unsigned char *fido_code, const int fido_code_len);

#endif

