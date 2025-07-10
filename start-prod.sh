#!/bin/bash

# Exit on error
set -e

echo "Starting Laravel API in PRODUCTION mode..."

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

# Make prod.sh executable
chmod +x prod.sh

# Start the services
echo "Starting Docker services for production..."
docker compose -f docker-compose.prod.yml down
docker compose -f docker-compose.prod.yml up -d

echo "Services started successfully!"
echo ""
echo "Your Laravel API is now available at:"
echo "  - https://immogestion.alemsafi.live"
echo "  - Local access: http://localhost:80"
echo ""
echo "PHPMyAdmin is available at:"
echo "  - http://localhost:8081"
echo ""
echo "To stop the services, run:"
echo "  docker compose -f docker-compose.prod.yml down"

