FROM php:8.4-fpm

# Keep file permissions the same as the host for the mounted volume.
RUN useradd -m -u 1000 ubuntu

RUN apt-get update && apt-get install -y --no-install-recommends \
        libfreetype6-dev \
        libjpeg-dev \
        libpng-dev \
        libzip-dev \
        libicu-dev \
        git \
        unzip \
        default-mysql-client \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install gd opcache pdo_mysql bcmath zip intl \
    && pecl install pcov \
    && docker-php-ext-enable pcov \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

RUN echo "php_admin_flag[log_errors] = On" >> /usr/local/etc/php-fpm.conf

COPY ./docker/php/headers.ini /usr/local/etc/php/conf.d/headers.ini
COPY ./docker/php/development.ini /usr/local/etc/php/conf.d/development.ini
COPY ./docker/php/upload.ini /usr/local/etc/php/conf.d/upload.ini
COPY ./docker/php/memory.ini /usr/local/etc/php/conf.d/memory.ini
COPY ./docker/start.sh /usr/local/bin/start
RUN chmod +x /usr/local/bin/start

WORKDIR /var/www

CMD ["/usr/local/bin/start"]
