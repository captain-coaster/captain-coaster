FROM php:8.4.13-fpm-alpine

# Install Git
RUN apk add --no-cache git

# Install PHP extensions
COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN install-php-extensions apcu gd opcache redis zip intl pdo_mysql xsl

ENV VERSION=v3.88.2

RUN wget https://github.com/FriendsOfPHP/PHP-CS-Fixer/releases/download/$VERSION/php-cs-fixer.phar -O php-cs-fixer \
    && chmod a+x php-cs-fixer \
    && mv php-cs-fixer /usr/local/bin/php-cs-fixer

COPY ./conf.d/docker-php.ini /usr/local/etc/php/conf.d/

WORKDIR /var/www

EXPOSE 9000
