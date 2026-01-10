# Use PHP 8.4 FPM (Laravel 10 / Symfony 8 compatible)
FROM php:8.4-fpm

# Set working directory
WORKDIR /app

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    zip \
    curl \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath zip

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Copy application code
COPY . /app

# Install PHP dependencies
RUN composer install --optimize-autoloader --no-dev --no-interaction

RUN php artisan migrate --force
RUN php artisan config:clear
RUN php artisan cache:clear
# Copy env example first
COPY .env.example .env

RUN php artisan key:generate --force

# Expose port (Railway uses $PORT automatically)
EXPOSE 8000

# Run migrations and start FPM (production-ready)
CMD php artisan migrate --force && php-fpm
