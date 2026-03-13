FROM php:8.2-apache

COPY . /var/www/html/

RUN mkdir -p /var/www/html/chats \
    && chmod -R 777 /var/www/html/chats \
    && mkdir -p /var/www/html/uploads \
    && chmod -R 777 /var/www/html/uploads

# Increase PHP upload limits
RUN echo "upload_max_filesize = 100M" >> /usr/local/etc/php/php.ini \
    && echo "post_max_size = 105M" >> /usr/local/etc/php/php.ini