FROM php:7.4-fpm-alpine

#RUN apk add --update-cache --no-cache \
#    php7-zip \
#    php7-intl \
#    php7-pecl-apcu \
#    php7-gd \
#    php7-pecl-imagick \
#    php7-opcache \
#    php7-pecl-redis \
#    php7-pdo_mysql

# Install PHP extensions
COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/

RUN install-php-extensions apcu gd imagick opcache redis zip intl pdo_mysql

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

EXPOSE 9000
