FROM php:8.2-fpm-alpine

# Install Git
RUN apk add --no-cache git

# Install PHP extensions
COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/

RUN install-php-extensions apcu gd imagick opcache redis zip intl pdo_mysql

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY ./conf.d/docker-php.ini /usr/local/etc/php/conf.d/

WORKDIR /var/www

EXPOSE 9000
