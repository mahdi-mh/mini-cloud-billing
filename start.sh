#!/usr/bin/env sh
# One command to get the whole system running: ./start.sh
# Needs Docker with compose. Nothing else on the machine.
set -e
cd "$(dirname "$0")"

[ -f .env ] || cp .env.example .env

# the containers run as you, so files in this folder stay yours
export WWWUSER="$(id -u)" WWWGROUP="$(id -g)"

# first run: no vendor folder yet, get it with a throw-away container
if [ ! -d vendor ]; then
    docker run --rm -u "$WWWUSER:$WWWGROUP" -v "$(pwd):/var/www/html" -w /var/www/html \
        laravelsail/php84-composer:latest composer install --ignore-platform-reqs --no-interaction
fi

docker compose up -d --build --wait mysql

# sail runs the app as the "sail" user
sail="docker compose exec -T -u sail laravel.test"

docker compose up -d --build
grep -q '^APP_KEY=.\+' .env || $sail php artisan key:generate --force
$sail php artisan migrate --seed --force
$sail sh -c 'npm install && npm run build'

echo
echo "Ready:"
echo "  app         http://localhost:${APP_PORT:-8085}   (owner@minicloud.test / password)"
echo "  phpMyAdmin  http://localhost:${PMA_PORT:-8086}"
echo "  fake infra  http://localhost:${INFRA_PORT:-8087}/infra/servers"
