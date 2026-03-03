FROM public.ecr.aws/docker/library/php:8.2-fpm

# Installer dépendances système et extensions PHP
RUN apt-get update && apt-get install -y \
    git \
    curl \
    zip \
    unzip \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    libicu-dev \
    nginx \
    supervisor \
    && docker-php-ext-install \
        pdo \
        pdo_mysql \
        mbstring \
        exif \
        pcntl \
        bcmath \
        gd \
        zip \
        intl

# Installer Composer
RUN curl -sS https://getcomposer.org/installer | php \
    && mv composer.phar /usr/local/bin/composer

WORKDIR /var/www

# Copier le projet
COPY . .

# Installer dépendances Laravel
RUN composer install --no-dev --optimize-autoloader

# Créer les dossiers Laravel obligatoires
RUN mkdir -p storage/framework/sessions \
    storage/framework/cache \
    storage/framework/views \
    bootstrap/cache \
    storage/logs

# Permissions correctes
RUN chown -R www-data:www-data /var/www \
    && chmod -R 775 storage bootstrap/cache

# Supprimer config nginx par défaut
RUN rm /etc/nginx/sites-enabled/default

# Copier le fichier .env.example
COPY .env.example .

# Copier config nginx
COPY docker/nginx.conf /etc/nginx/conf.d/default.conf

# Copier config supervisor
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Copier entrypoint
COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

EXPOSE 80

VOLUME /var/log

ENTRYPOINT ["/entrypoint.sh"]
