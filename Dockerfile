FROM php:8.2-apache

RUN docker-php-ext-install pdo pdo_mysql

# Serve public/ as the document root instead of the project root.
RUN sed -ri -e 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/*.conf /etc/apache2/apache2.conf

COPY . /var/www/html
