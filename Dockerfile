FROM php:8.2-fpm

WORKDIR /var/www/html

COPY . .

EXPOSE 8000


CMD ["php", "artisan", "serve", "--host", "0.0.0.0", "--port", "8000"]
