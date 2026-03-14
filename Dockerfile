FROM php:8.2-apache

# Install dependencies for Composer and MongoDB extension
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libssl-dev \
    && pecl install mongodb \
    && docker-php-ext-enable mongodb \
    && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

COPY . /var/www/html/

# Install PHP dependencies
RUN cd /var/www/html/ && composer install --no-dev --optimize-autoloader


RUN mkdir -p /var/www/html/chats \
    && chmod -R 777 /var/www/html/chats \
    && mkdir -p /var/www/html/uploads \
    && chmod -R 777 /var/www/html/uploads

# Increase PHP upload limits
RUN echo "upload_max_filesize = 100M" >> /usr/local/etc/php/php.ini \
    && echo "post_max_size = 105M" >> /usr/local/etc/php/php.ini