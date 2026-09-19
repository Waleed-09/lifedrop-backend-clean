FROM php:8.2-cli

RUN apt-get update && apt-get install -y \
    git \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    zip \
    unzip \
    ca-certificates \
    && docker-php-ext-install pdo pdo_mysql mbstring bcmath zip \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

COPY . .

# Certs folder se ca.pem copy karke system path par set karein
RUN mkdir -p /etc/ssl/certs/aiven \
    && cp certs/ca.pem /etc/ssl/certs/aiven/ca.pem

RUN touch database/database.sqlite \
    && composer install --no-dev --optimize-autoloader --no-interaction --no-scripts --ignore-platform-reqs \
    && php artisan package:discover --ansi

EXPOSE 8080

CMD php artisan migrate --force && php artisan serve --host=0.0.0.0 --port=8080