#!/bin/bash

# Exit script if any command fails
set -e

echo "Starting backend setup script for PRODUCTION..."

# First, update the DB_HOST in the .env file to point to the AWS RDS
sed -i 's/DB_HOST=.*/DB_HOST=erp-studio3d.cng8secmmw73.eu-north-1.rds.amazonaws.com/g' .env
sed -i 's/DB_USERNAME=.*/DB_USERNAME=admin/g' .env
sed -i 's/DB_PASSWORD=.*/DB_PASSWORD=Kilo15.35/g' .env
sed -i 's/APP_ENV=.*/APP_ENV=production/g' .env
sed -i 's/APP_DEBUG=.*/APP_DEBUG=false/g' .env

echo "Modified database configuration to use AWS RDS database"

# Wait for the MySQL server to be ready
echo "Waiting for MySQL to be ready..."
MAX_RETRIES=30
count=0

while ! php -r "try { \$dbh = new PDO('mysql:host=erp-studio3d.cng8secmmw73.eu-north-1.rds.amazonaws.com;port=3306', 'admin', 'Kilo15.35'); echo 'Connected successfully'; } catch(PDOException \$e) { exit(1); }" 2>/dev/null; do
    count=$((count+1))
    if [ $count -gt $MAX_RETRIES ]; then
        echo "Error: MySQL did not become ready in time."
        exit 1
    fi
    echo "MySQL not ready yet... waiting 2 seconds"
    sleep 2
done

echo "MySQL server is now available."

php artisan migrate --force
echo "Database migrated successfully"

php artisan db:seed --force
echo "Database seeded successfully"

php artisan passport:install --force
echo "Passport installed successfully"

if php artisan | grep -q "pusher:install"; then
    php artisan pusher:install
    echo "Pusher installed successfully"
else
    echo "Pusher command not found, skipping"
fi

echo "Starting Laravel server on port 8000..."
php artisan serve --host=0.0.0.0 --port=8000
