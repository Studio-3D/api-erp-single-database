#!/bin/bash

# Exit on error
set -e

echo "Starting Laravel API in PRODUCTION mode with SSL support..."

# Check if Docker is installed
if ! [ -x "$(command -v docker)" ]; then
  echo 'Error: Docker is not installed.' >&2
  exit 1
fi

# Check if Docker Compose is installed
if ! [ -x "$(command -v docker compose)" ]; then
  echo 'Error: Docker Compose is not installed.' >&2
  exit 1
fi

# Make sure the SSL directory exists
mkdir -p nginx/ssl

# Check if SSL certificates exist, if not generate them
if [ ! -f nginx/ssl/server.crt ] || [ ! -f nginx/ssl/server.key ]; then
  echo "SSL certificates not found. Generating self-signed certificates..."
  openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
    -keyout nginx/ssl/server.key \
    -out nginx/ssl/server.crt \
    -subj "/C=US/ST=State/L=City/O=Organization/CN=ec2-16-16-56-93.eu-north-1.compute.amazonaws.com"
  echo "Self-signed certificates generated."
fi

# Make prod.sh executable
chmod +x prod.sh

# Start the services
echo "Starting Docker services for production..."
docker compose -f docker-compose.prod.yml down
docker compose -f docker-compose.prod.yml up -d

echo "Services started successfully!"
echo ""
echo "Your Laravel API is now available at:"
echo "  - HTTP: http://localhost:80"
echo "  - HTTPS: https://ec2-13-61-150-41.eu-north-1.compute.amazonaws.com:443"
echo ""
echo "Note: Since we're using self-signed certificates, you may need to accept"
echo "      security warnings in your browser when accessing the HTTPS URL."
echo ""
echo "To stop the services, run:"
echo "  docker compose -f docker-compose.prod.yml down"
