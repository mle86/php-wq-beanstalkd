FROM php:7.1-cli

RUN \
	    apt-get update && \
	    apt-get install -y  beanstalkd

VOLUME ["/mnt"]

ADD docker-start.sh /start.sh
RUN chmod +x /start.sh
ENTRYPOINT ["/start.sh"]

USER nobody
WORKDIR /mnt
CMD ["vendor/bin/phpunit"]
