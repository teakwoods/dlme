FROM wordpress:php8.2-fpm

WORKDIR /var/www/html

RUN docker-php-ext-install mysqli

# Optional: install composer in container
# RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
