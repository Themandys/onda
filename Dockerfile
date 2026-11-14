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

# Controlador de Postgres, para usar una base de datos externa y persistente
# (variable DB_URL). Es opcional: si no se pudiera instalar, la imagen se
# construye igual y la app funciona con SQLite.
RUN (apk add --no-cache libpq-dev && docker-php-ext-install pdo_pgsql) \
    || echo "AVISO: pdo_pgsql no disponible; solo se podra usar SQLite"

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Valores de producción de serie; las variables definidas en el panel del
# hosting tienen prioridad sobre estas. La sesión viaja en una cookie cifrada
# y la caché va a disco: así no dependen de la base de datos, que en un plan
# gratuito sin disco persistente se vacía en cada reinicio.
ENV APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr \
    SESSION_DRIVER=cookie \
    CACHE_STORE=file

WORKDIR /app
COPY . .

# Instalar paquetes de PHP y compilar el frontend con Vite
RUN composer install --no-dev --optimize-autoloader --no-interaction \
    && npm ci \
    && npm run build \
    && rm -rf node_modules

# La clave de la app se genera al construir la imagen y no en cada arranque:
# un reinicio ya no invalida las sesiones ni las páginas que estaban abiertas.
# Si el hosting define APP_KEY, se usa esa.
RUN cp .env.example .env && php artisan key:generate --force

# Script de arranque: prepara carpetas, migra la base de datos y sirve la app.
# Con DB_URL apuntando a Postgres se usa esa base de datos (persistente);
# sin ella, SQLite dentro del contenedor.
RUN printf '#!/bin/sh\n\
set -e\n\
if [ ! -f .env ]; then\n\
    cp .env.example .env\n\
    php artisan key:generate --force\n\
fi\n\
case "${DB_URL:-}" in\n\
    postgres*) export DB_CONNECTION=pgsql ;;\n\
esac\n\
mkdir -p database storage/app/public/avatars storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs\n\
if [ "${DB_CONNECTION:-sqlite}" = "sqlite" ]; then\n\
    touch database/database.sqlite\n\
fi\n\
php artisan migrate --force\n\
php artisan storage:link --force || true\n\
php artisan optimize || php artisan optimize:clear\n\
export PHP_CLI_SERVER_WORKERS="${PHP_CLI_SERVER_WORKERS:-4}"\n\
exec php artisan serve --no-reload --host=0.0.0.0 --port="${PORT:-8080}"\n' > /usr/local/bin/start-onda \
    && chmod +x /usr/local/bin/start-onda

EXPOSE 8080

CMD ["/usr/local/bin/start-onda"]
