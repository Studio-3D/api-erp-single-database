#!/bin/bash

echo "Fixing Laravel root for Elastic Beanstalk..."

cd /var/app/current || exit 1

# Supprimer ancien index.php s'il existe
if [ -f index.php ]; then
  rm -f index.php
fi

# Créer le lien symbolique
ln -s public/index.php index.php

# Permissions
chown -h webapp:webapp index.php

echo "Laravel root fixed."
