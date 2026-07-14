FROM php:8.2-apache

RUN apt-get update && apt-get install -y \
        libpq-dev \
        libzip-dev \
        unzip \
        git \
    && docker-php-ext-install pdo pdo_pgsql pgsql \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

COPY vhost.conf /etc/apache2/sites-available/000-default.conf

WORKDIR /var/www/html
