FROM php:8.3-fpm-alpine

RUN apk add --no-cache \
    icu-dev \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    libzip-dev \
    oniguruma-dev \
    linux-headers \
    git \
    curl \
    unzip \
    postgresql-dev \
    bash

RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS && \
    pecl install redis && \
    docker-php-ext-enable redis && \
    apk del .build-deps && \
    docker-php-ext-configure gd --with-freetype --with-jpeg && \
    docker-php-ext-install -j$(nproc) \
    intl \
    gd \
    zip \
    mbstring \
    opcache \
    pdo_pgsql \
    pcntl

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY docker/php/custom.ini /usr/local/etc/php/conf.d/custom.ini
COPY docker/php/www.conf /usr/local/etc/php-fpm.d/www.conf

RUN adduser -u 1000 -D -H -s /bin/sh appuser

WORKDIR /var/www/html

COPY . .

RUN mkdir -p var/cache var/log var/sessions /app/storage/transfers /app/storage/previews /app/storage/tmp /app/storage/quarantine && \
    chown -R appuser:appuser var /app/storage && \
    chmod -R 775 var /app/storage

RUN if [ -f composer.json ]; then composer install --no-dev --optimize-autoloader --no-interaction || true; fi

USER root

EXPOSE 9000

CMD ["php-fpm"]