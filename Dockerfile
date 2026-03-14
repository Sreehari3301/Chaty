FROM php:8.2-apache

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libssl-dev \
    libcurl4-openssl-dev \
    pkg-config \
    && rm -rf /var/lib/apt/lists/*

# Install MongoDB PHP extension separately
RUN pecl install mongodb && docker-php-ext-enable mongodb

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

COPY . /var/www/html/

# Install PHP dependencies - ignore platform reqs since ext-mongodb is already installed above
ENV COMPOSER_ALLOW_SUPERUSER=1
RUN cd /var/www/html/ && composer install --no-dev --optimize-autoloader --no-interaction --ignore-platform-reqs

RUN mkdir -p /var/www/html/chats \
    && chmod -R 777 /var/www/html/chats \
    && mkdir -p /var/www/html/uploads \
    && chmod -R 777 /var/www/html/uploads

# Increase PHP upload limits
RUN echo "upload_max_filesize = 100M" >> /usr/local/etc/php/php.ini \
    && echo "post_max_size = 105M" >> /usr/local/etc/php/php.ini