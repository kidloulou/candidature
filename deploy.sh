#!/bin/bash
set -e
set -x

echo "APP_ENV=$APP_ENV"

echo "→ Installation des dépendances (sans dev)..."
composer install --no-dev --optimize-autoloader

echo "→ Nettoyage du cache Symfony (prod)..."
php bin/console cache:clear --env=prod --no-debug

echo "→ Application des migrations Doctrine..."
php bin/console doctrine:migrations:migrate --no-interaction

echo "✓ Déploiement prêt."
