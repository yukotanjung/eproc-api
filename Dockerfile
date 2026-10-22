# Gunakan sintaks Dockerfile baru untuk fitur-fitur build
# syntax=docker/dockerfile:1.4

# ===========================
# ===== Stage 1: vendor =====
# ===========================
# Stage ini hanya untuk menginstal dependensi Composer
FROM php:8.2-fpm-alpine AS vendor

# Instal dependensi minimum untuk Composer
# Perhatikan bahwa ekstensi PHP yang akan diinstal di Stage 2 sudah tidak perlu di sini
RUN set -eux; \
    apk add --no-cache git bash zip unzip

# Salin binari Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
ENV COMPOSER_CACHE_DIR=/tmp/composer-cache

# Salin file Composer terlebih dahulu untuk layer cache yang lebih baik
COPY composer.json ./

# Instal dependensi (vendor)
# Asumsi Anda punya composer.lock. Jika tidak, hapus composer.lock dari baris COPY di atas.
RUN composer install --no-dev --no-interaction --prefer-dist --no-scripts --no-progress

# Salin kode aplikasi (hanya bagian yang diperlukan untuk vendor)
COPY . .
# Dump autoload dan hapus cache Composer
RUN composer dump-autoload --optimize --no-dev && rm -rf $COMPOSER_CACHE_DIR

# ============================
# ===== Stage 2: runtime =====
# ============================
# Stage produksi yang ramping
FROM php:8.2-fpm-alpine

# Instal tools runtime & dependensi PHP
# Sertakan openssl-dev di .build-deps untuk mendukung TLS/SSL pada libmongoc (yang mengatasi error Anda)
RUN set -eux; \
    # Instal paket runtime yang diperlukan
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
    # Instal dependensi build untuk ekstensi PHP
    apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS \
        libzip-dev \
        libjpeg-turbo-dev \
        libpng-dev \
        libwebp-dev \
        freetype-dev \
        openssl-dev; \
    \
    # Konfigurasi dan instal ekstensi PHP
    docker-php-ext-configure gd \
        --with-freetype \
        --with-jpeg \
        --with-webp; \
    docker-php-ext-install -j"$(nproc)" gd zip pdo_mysql; \
    \
    # Instal ekstensi MongoDB dengan dukungan SSL/TLS
    pecl install mongodb-1.19.2; \
    docker-php-ext-enable mongodb; \
    \
    # Bersihkan dependensi build untuk mengurangi ukuran image
    apk del .build-deps

# Direktori Supervisor
RUN mkdir -p /var/log/supervisor /etc/supervisor/conf.d

WORKDIR /var/www/html

# Salin kode aplikasi dan vendor dari stage sebelumnya
# Pastikan semua file disalin dengan owner www-data
COPY --from=vendor --chown=www-data:www-data /var/www/html/ /var/www/html/

# Permissions untuk Laravel storage/cache
RUN set -eux; \
    mkdir -p storage/framework/{cache,sessions,views} bootstrap/cache; \
    # Lebih aman menggunakan chmod 775 untuk direktori dan 664 untuk file, tetapi ini adalah yang paling umum
    chown -R www-data:www-data storage bootstrap/cache; \
    find storage -type d -exec chmod 775 {} \; || true; \
    find bootstrap/cache -type d -exec chmod 775 {} \; || true; \
    find storage -type f -exec chmod 664 {} \; || true; \
    find bootstrap/cache -type f -exec chmod 664 {} \; || true

# Konfigurasi PHP-FPM: Atur user/group dan pastikan clear_env=no
RUN sed -i -E 's|^user = .*|user = www-data|; s|^group = .*|group = www-data|' /usr/local/etc/php-fpm.d/www.conf \
    && sed -i -E 's|^;?clear_env = .*|clear_env = no|' /usr/local/etc/php-fpm.d/www.conf

# Salin konfigurasi Nginx & Supervisor
# Asumsi path `dockerfiles/` ada di root context build
COPY ./dockerfiles/nginx/default.conf /etc/nginx/http.d/default.conf
COPY ./dockerfiles/supervisord.conf /etc/supervisord.conf
COPY ./dockerfiles/supervisor/*.conf /etc/supervisor/conf.d/

# Expose port PHP-FPM
EXPOSE 9000

ENV APP_ENV=production

# Jalankan Supervisor (mengelola php-fpm / queue / scheduler)
CMD ["supervisord", "-c", "/etc/supervisord.conf"]
