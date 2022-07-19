#! /bin/sh

if [ ! -f "Makefile" ] || [ ! -f "test.c" ] ; then
	echo "Missing files for testing: Makefile and test.c"
	exit 1
fi

make test
if [ -f "test.o" ] ; then
	rm test.o
fi
