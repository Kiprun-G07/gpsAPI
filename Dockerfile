# Use PHP 8.4 FPM (Laravel 10)
FROM php:8.4-fpm

WORKDIR /app

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git unzip libonig-dev libxml2-dev libzip-dev zip curl \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath zip

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Copy app code first
COPY . /app

# Copy env example and create .env
COPY .env.example .env

# Install PHP dependencies
RUN composer install --optimize-autoloader --no-dev --no-interaction

# Generate app key (requires .env)
RUN php artisan key:generate --force

# Clear cache/config
#RUN php artisan config:clear
#RUN php artisan cache:clear

# Expose port
EXPOSE 8000

# Run migrations + start FPM at container start
CMD php artisan migrate --force && php-fpm
