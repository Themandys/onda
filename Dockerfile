FROM php:8.4-cli-alpine

# Dependencias del sistema y extensiones de PHP necesarias para Onda
RUN apk add --no-cache \
    bash \
    curl \
    git \
    icu-dev \
    libpng-dev \
    libwebp-dev \
    libjpeg-turbo-dev \
    libzip-dev \
    nodejs \
    npm \
     oniguruma-dev \
    sqlite \
    sqlite-dev \
    unzip \
    && docker-php-ext-configure gd --with-jpeg --with-webp \
    && docker-php-ext-install -j$(nproc) \
        pdo_sqlite \
        mbstring \
        exif \
        pcntl \
        bcmath \
        gd \
        intl \
        zip

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app
COPY . .

# Instalar paquetes de PHP y compilar el frontend con Vite
RUN composer install --no-dev --optimize-autoloader --no-interaction \
    && npm ci \
    && npm run build \
    && rm -rf node_modules

# Script de arranque automático (crea .env, APP_KEY, SQLite y migra solo)
RUN printf '#!/bin/sh\n\
set -e\n\
if [ ! -f .env ]; then\n\
    cp .env.example .env\n\
    php artisan key:generate --force\n\
fi\n\
mkdir -p database storage/app/public/avatars storage/framework/cache storage/framework/sessions storage/framework/views storage/logs\n\
touch database/database.sqlite\n\
php artisan migrate --force\n\
php artisan storage:link --force || true\n\
exec php artisan serve --host=0.0.0.0 --port="${PORT:-8080}"\n' > /usr/local/bin/start-onda \
    && chmod +x /usr/local/bin/start-onda

EXPOSE 8080

CMD ["/usr/local/bin/start-onda"]
