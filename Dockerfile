# see TESTING.md

FROM php:7.1-cli

RUN \
	    apt-get update && \
	    apt-get install -y  beanstalkd

VOLUME ["/mnt"]

# This should prevent the tests from running on a host with a real Beanstalkd instance.
ENV BEANSTALKD_PORT 11382

ADD docker-start.sh /start.sh
RUN chmod +x /start.sh
ENTRYPOINT ["/start.sh"]

USER nobody
WORKDIR /mnt
CMD ["vendor/bin/phpunit"]
