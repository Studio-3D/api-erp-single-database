FROM public.ecr.aws/docker/library/php:8.2-fpm

# Installation des dépendances
RUN apt-get update && apt-get install -y \
    git \
    curl \
    unzip \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    zip \
    nginx \
    supervisor \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip

# Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Configuration Nginx
RUN rm /etc/nginx/sites-enabled/default
COPY docker/nginx.conf /etc/nginx/sites-available/laravel
RUN ln -sf /etc/nginx/sites-available/laravel /etc/nginx/sites-enabled/

# Configuration Supervisor
COPY docker/supervisor.conf /etc/supervisor/conf.d/supervisor.conf

# Répertoire de travail
WORKDIR /var/www/html

# Copie des fichiers
COPY . .

# Installation PHP
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Permissions
RUN mkdir -p storage bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

# Port exposé
EXPOSE 80

# Commande de démarrage
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisor.conf"]