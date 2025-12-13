FROM php:8.4-fpm
RUN apt-get update && apt-get install -y \
    zlib1g-dev \
    libzip-dev \
    libpq-dev \
    unzip
RUN docker-php-ext-install zip pdo pdo_pgsql pgsql