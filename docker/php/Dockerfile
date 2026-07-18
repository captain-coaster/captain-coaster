FROM php:8.5.4-fpm-alpine

# Install Git
RUN apk add --no-cache git

# Install PHP extensions
COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN install-php-extensions apcu gd opcache redis zip intl pdo_mysql xsl

COPY ./conf.d/docker-php.ini /usr/local/etc/php/conf.d/

WORKDIR /var/www

EXPOSE 9000
