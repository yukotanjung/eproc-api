FROM php:8.2-fpm-alpine


RUN mkdir -p /var/www/html
RUN chown -R www-data:www-data /var/www/html
WORKDIR /var/www/html

# Create the storage directory and set permissions
RUN mkdir -p storage
RUN chown -R www-data:www-data storage
RUN chmod -R 775 storage

RUN apk add --no-cache supervisor

RUN mkdir -p /etc/supervisor/conf.d
RUN mkdir -p /var/log/supervisor /var/run

# Copy supervisor config
COPY supervisord.conf /etc/supervisord.conf
COPY supervisor/*.conf /etc/supervisor/conf.d/

COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer

RUN docker-php-ext-install pdo pdo_mysql

#CMD ["php-fpm", "-y", "/usr/local/etc/php-fpm.conf", "-R"]
