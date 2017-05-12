#!/bin/sh

# see TESTING.md

if [ ! -f /.dockerenv ]; then
	echo "This script is supposed to be run inside a Docker container."  >&2
	echo "Try 'make test' instead."  >&2
	exit 1
fi

beanstalkd  -u daemon  -p $BEANSTALKD_PORT  &

exec "$@"
