#!/bin/sh

beanstalkd  -u daemon  -p $BEANSTALKD_PORT  &

exec "$@"
