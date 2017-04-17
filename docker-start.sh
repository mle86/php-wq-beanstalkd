#!/bin/sh

beanstalkd  -u daemon  &

exec "$@"
