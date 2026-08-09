FROM php:8.2-fpm

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git curl unzip libpq-dev libonig-dev libzip-dev zip \
    && docker-php-ext-install pdo pdo_mysql mbstring zip

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

# Copy app files
COPY . .

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader

# Laravel setup
RUN php artisan config:clear && \
    php artisan route:clear && \
    php artisan view:clear

# Serve HTTP on Railway's injected $PORT. php-fpm alone speaks FastCGI, not HTTP,
# so the platform proxy can't reach it — use artisan serve to bind an HTTP port.
# Run migrations at startup so the schema is ready on each deploy.
CMD php artisan migrate --force && php artisan serve --host=0.0.0.0 --port=${PORT:-8000}
