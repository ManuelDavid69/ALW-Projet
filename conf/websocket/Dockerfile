FROM php:8.2-cli

# Workerman a besoin de pcntl (processus) ; mbstring reste utile pour l'UTF-8.
RUN apt-get update \
    && apt-get install -y --no-install-recommends libonig-dev unzip \
    && docker-php-ext-install pcntl mbstring \
    && rm -rf /var/lib/apt/lists/*

# Composer sert uniquement à installer Workerman dans l'image.
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY ws/composer.json ./
RUN composer install --no-dev --no-interaction --prefer-dist

COPY ws/ ./

EXPOSE 50200

CMD ["php", "server.php", "start"]
