CC=gcc
CFLAGS=-Wall
IN=include
OBJS= src/fido_reader.c src/fido_utils.c
all:
	$(CC) main.c $(OBJS) $(CFLAGS)
elf:
	$(CC) main.c $(OBJS) $(CFLAGS) -no-pie
test:
	$(CC) test.c src/fido_test.c src/fido_utils.c $(CFLAGS) -o test.o
	./test.o
	rm test.o
clean:
	rm a.out
