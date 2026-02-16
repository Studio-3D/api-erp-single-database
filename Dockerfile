FROM public.ecr.aws/docker/library/php:8.2-fpm

# Installer dépendances système
RUN apt-get update && apt-get install -y \
    git \
    curl \
    zip \
    unzip \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    nginx \
    supervisor \
    && docker-php-ext-install pdo pdo_mysql mbstring exif pcntl bcmath gd zip

# Installer Composer
RUN curl -sS https://getcomposer.org/installer | php \
    && mv composer.phar /usr/local/bin/composer

WORKDIR /var/www

# Copier le projet
COPY . .

# Installer dépendances Laravel
RUN composer install --no-dev --optimize-autoloader

# Générer clé application
RUN php artisan key:generate

# Générer clés OAuth Passport
RUN php artisan passport:keys

# 🔥 CRÉER LES DOSSIERS LARAVEL OBLIGATOIRES
RUN mkdir -p storage/framework/sessions \
    storage/framework/cache \
    storage/framework/views \
    bootstrap/cache

# 🔥 PERMISSIONS CORRECTES
RUN chown -R www-data:www-data /var/www \
    && chmod -R 775 storage bootstrap/cache

# Supprimer config nginx par défaut
RUN rm /etc/nginx/sites-enabled/default

# Copier config nginx
COPY docker/nginx.conf /etc/nginx/conf.d/default.conf

# Copier config supervisor
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

EXPOSE 80

CMD ["/usr/bin/supervisord"]
