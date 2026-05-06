#!/bin/sh
set -e

cd /var/www

echo "🚀 Initializing Laravel..."

# Copier .env si absent
if [ ! -f .env ]; then
    echo "Creating .env from example"
    cp .env.example .env
fi

# Générer APP_KEY si vide
if ! grep -q "APP_KEY=base64:" .env; then
    echo "Generating APP_KEY..."
    php artisan key:generate --force
fi

# Générer clés Passport si absentes
if [ ! -f storage/oauth-private.key ]; then
    echo "Generating Passport keys..."
    php artisan passport:keys --force
fi

# Créer le dossier logs s'il n'existe pas
mkdir -p storage/logs
chown -R www-data:www-data storage
chmod -R 775 storage

# Permissions runtime
chown -R www-data:www-data storage bootstrap/cache

# Lancer supervisord au premier plan
exec /usr/bin/supervisord -n