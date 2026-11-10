#!/usr/bin/env bash
# Run on the host as an operator with Docker access, from the repository root.
set -Eeuo pipefail
cd "$(dirname "$0")/.."
exec 9>/tmp/minicloud-deploy.lock
flock -n 9 || { echo 'Another deployment is running.' >&2; exit 1; }
[ -f .env.production ] || { echo 'Create .env.production first.' >&2; exit 1; }
export RELEASE="$(git rev-parse --short=12 HEAD)"
dc() { docker compose --env-file .env.production -f compose.production.yaml "$@"; }
dc config --quiet
dc build web
# Reuse one immutable image across all application processes.
dc up -d --wait mysql
# Populate volumes and run migrations before accepting requests or starting workers.
dc run --rm --no-deps --user root web sh -c 'mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs; chown -R www-data:www-data storage'
dc run --rm --no-deps --user root infra sh -c 'mkdir -p storage/infra storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs; touch storage/infra/infra.sqlite; chown -R www-data:www-data storage'
# Existing deployments pause writers during schema changes. Failure deliberately leaves them stopped.
dc stop queue scheduler web infra
dc run --rm --no-deps --user www-data web php artisan migrate --force
dc run --rm --no-deps --user www-data infra php artisan migrate --database=infra --path=Modules/FakeInfra/database/infra --force
dc up -d --no-build --wait
printf 'Deployed commit %s\n' "$RELEASE"
dc ps
