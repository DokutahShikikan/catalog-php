FROM php:8.2-cli

RUN apt-get update && apt-get install -y \
    libpq-dev \
    && docker-php-ext-install pgsql pdo_pgsql \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app

COPY www/ /app

EXPOSE 8080

CMD ["php", "-S", "0.0.0.0:8080", "-t", "/app"]
