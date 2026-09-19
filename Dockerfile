FROM php:8.2-cli

RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    zip \
    unzip \
    ca-certificates \
    && docker-php-ext-install pdo pdo_mysql mbstring bcmath zip \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Aiven ka official CA certificate download karein
RUN mkdir -p /etc/ssl/certs/aiven \
    && curl -sS https://certs.aiven.com/cacert.pem -o /etc/ssl/certs/aiven/ca.pem

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

COPY . .

RUN touch database/database.sqlite \
    && composer install --no-dev --optimize-autoloader --no-interaction --no-scripts --ignore-platform-reqs \
    && php artisan package:discover --ansi

EXPOSE 8080

CMD php artisan migrate --force && php artisan serve --host=0.0.0.0 --port=8080