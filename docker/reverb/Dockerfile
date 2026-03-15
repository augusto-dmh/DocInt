FROM php:8.3-cli-bookworm

ARG USER_ID=1000
ARG GROUP_ID=1000

RUN apt-get update && apt-get install -y --no-install-recommends \
    libonig-dev \
    libxml2-dev \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath \
        mbstring \
        pcntl \
        pdo_mysql \
        sockets \
    && pecl install redis-6.2.0 \
    && docker-php-ext-enable redis \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY docker/php/php.ini /usr/local/etc/php/conf.d/99-docintern.ini

RUN groupadd -g "${GROUP_ID}" docintern \
    && useradd -u "${USER_ID}" -g docintern -m -s /bin/bash docintern

WORKDIR /var/www/html

USER docintern

EXPOSE 8080

CMD ["php", "artisan", "reverb:start", "--host=0.0.0.0", "--port=8080", "--no-interaction"]
