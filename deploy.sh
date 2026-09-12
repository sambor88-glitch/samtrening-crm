#!/usr/bin/env bash
#
# Skrypt wdrożeniowy — wklejany w Forge (Site → Apps → Deploy Script) albo
# uruchamiany ręcznie na serwerze. Wszystko, co po nim zostaje, ma działać
# po restarcie maszyny bez niczyjej pomocy.
#
# Kolejność ma znaczenie: migracje przed przebudową cache'u, restart workera
# na końcu — inaczej stary worker wykonuje zadania starym kodem.

set -euo pipefail

cd "$(dirname "$0")"

php artisan down --render="errors::503" --retry=15 || true

git pull origin main

composer install --no-interaction --prefer-dist --optimize-autoloader --no-dev

npm ci
npm run build

php artisan migrate --force

php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# Worker kolejki bierze nowy kod; Forge trzyma go pod nadzorem, więc wstanie sam.
php artisan queue:restart

php artisan up

# Kontrola po wdrożeniu — jeśli któreś nie przejdzie, wdrożenie było nieudane.
php artisan about --only=environment
php artisan schedule:list
