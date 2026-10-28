# syntax=docker/dockerfile:1.4

FROM php:8.4-fpm-alpine AS vendor

RUN set -eux; \
    apk add --no-cache git bash zip unzip

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
ENV COMPOSER_CACHE_DIR=/tmp/composer-cache

COPY composer.json composer.lock* ./

# Skip platform checks here because PHP extensions are compiled only in the runtime stage.
RUN composer install --no-dev --no-interaction --prefer-dist --no-scripts --no-progress --ignore-platform-reqs

COPY . .
RUN composer dump-autoload --optimize --no-dev && rm -rf $COMPOSER_CACHE_DIR

FROM php:8.4-fpm-alpine

RUN set -eux; \
    apk add --no-cache \
        supervisor \
        curl \
        bash \
        nginx \
        libzip \
        openssl \
        libjpeg-turbo \
        libpng \
        libwebp \
        freetype; \
    \
    apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS \
        libzip-dev \
        libjpeg-turbo-dev \
        libpng-dev \
        libwebp-dev \
        freetype-dev; \
    \
    docker-php-ext-configure gd \
        --with-freetype \
        --with-jpeg \
        --with-webp; \
    docker-php-ext-install -j"$(nproc)" gd zip pdo_mysql; \
    \
    apk del .build-deps

RUN mkdir -p /var/log/supervisor /etc/supervisor/conf.d

WORKDIR /var/www/html

COPY --from=vendor --chown=www-data:www-data /var/www/html/ /var/www/html/

RUN set -eux; \
    mkdir -p storage/framework/{cache,sessions,views} bootstrap/cache; \
    chown -R www-data:www-data storage bootstrap/cache; \
    find storage -type d -exec chmod 775 {} \; || true; \
    find bootstrap/cache -type d -exec chmod 775 {} \; || true; \
    find storage -type f -exec chmod 664 {} \; || true; \
    find bootstrap/cache -type f -exec chmod 664 {} \; || true

RUN sed -i -E 's|^user = .*|user = www-data|; s|^group = .*|group = www-data|' /usr/local/etc/php-fpm.d/www.conf \
    && sed -i -E 's|^;?clear_env = .*|clear_env = no|' /usr/local/etc/php-fpm.d/www.conf

COPY ./dockerfiles/nginx/default.conf /etc/nginx/http.d/default.conf
COPY ./dockerfiles/supervisord.conf /etc/supervisord.conf
COPY ./dockerfiles/supervisor/*.conf /etc/supervisor/conf.d/

EXPOSE 9000

ENV APP_ENV=production

CMD ["supervisord", "-c", "/etc/supervisord.conf"]
