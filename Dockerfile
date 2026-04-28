FROM public.ecr.aws/docker/library/php:8.2-cli

WORKDIR /var/www

COPY . .

RUN apt-get update && apt-get install -y \
    git unzip libzip-dev \
    && docker-php-ext-install pdo pdo_mysql zip

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

RUN composer install --no-dev --optimize-autoloader

RUN cp .env.example .env

EXPOSE 80

CMD php artisan serve --host=0.0.0.0 --port=80