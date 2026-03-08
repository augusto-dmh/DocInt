FROM node:20-alpine

RUN apk add --no-cache \
    git \
    php83 \
    php83-bcmath \
    php83-ctype \
    php83-curl \
    php83-dom \
    php83-fileinfo \
    php83-iconv \
    php83-json \
    php83-mbstring \
    php83-openssl \
    php83-pcntl \
    php83-pdo \
    php83-pdo_mysql \
    php83-pecl-redis \
    php83-phar \
    php83-session \
    php83-simplexml \
    php83-tokenizer \
    php83-xml \
    php83-xmlwriter \
    php83-zip \
    && ln -sf /usr/bin/php83 /usr/bin/php

WORKDIR /var/www/html

EXPOSE 5173
