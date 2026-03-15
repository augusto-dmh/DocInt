FROM php:8.3-fpm-bookworm

ARG USER_ID=1000
ARG GROUP_ID=1000

RUN apt-get update && apt-get install -y --no-install-recommends \
    git \
    curl \
    zip \
    unzip \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    libcurl4-openssl-dev \
    libssl-dev \
    default-mysql-client \
    supervisor \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql \
        mbstring \
        exif \
        pcntl \
        bcmath \
        gd \
        zip \
        sockets \
        opcache \
    && pecl install redis-6.0.2 \
    && docker-php-ext-enable redis \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2.7 /usr/bin/composer /usr/bin/composer
COPY docker/php/php.ini /usr/local/etc/php/conf.d/custom.ini
COPY docker/workers/supervisord.conf /etc/supervisor/conf.d/docintern-workers.conf

RUN groupadd -g ${GROUP_ID} docintern \
    && useradd -u ${USER_ID} -g docintern -m docintern

WORKDIR /var/www/html

CMD ["/usr/bin/supervisord", "-n", "-c", "/etc/supervisor/conf.d/docintern-workers.conf"]
