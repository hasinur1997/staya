#!/usr/bin/env bash
#
# Runs on the cPanel server after the files are synced (see
# .github/workflows/deploy.yml). Safe to run by hand from the app directory:
#
#   PHP_BIN=/opt/cpanel/ea-php84/root/usr/bin/php bash deploy/post-deploy.sh
#
# If the migration fails the site is left in maintenance mode on purpose:
# the new code is live but the database is not ready for it.

set -euo pipefail

cd "$(dirname "${BASH_SOURCE[0]}")/.."

PHP_BIN="${PHP_BIN:-php}"
artisan() { "$PHP_BIN" artisan "$@"; }

if [ ! -f .env ]; then
    echo "Missing .env in $(pwd). Set the ENV_FILE variable in the production environment and deploy again." >&2
    exit 1
fi

# storage/ is never synced, so make sure the folders Laravel writes to exist.
mkdir -p storage/app/private storage/framework/cache/data storage/framework/sessions \
    storage/framework/views storage/logs bootstrap/cache
chmod -R u+rwX,g+rwX storage bootstrap/cache

artisan down --retry=60 --refresh=15 || true

# The config cache from the previous deploy lacks new config files and any
# changed .env values, so clear it before anything reads configuration.
artisan optimize:clear

artisan migrate --force

# Roles, permissions and the settings row (idempotent), then the first Admin
# from STAYA_OWNER_* in .env. The owner is only created while no Admin exists.
artisan db:seed --class=AccessControlSeeder --force
artisan db:seed --class=MessSettingsSeeder --force
artisan staya:create-owner

# Rebuild the config, route, event and view caches from the new code.
artisan optimize

# Queue workers keep the old code in memory until they are told to restart.
artisan queue:restart

artisan up

echo "Deployed $(git rev-parse --short HEAD 2>/dev/null || echo 'release') at $(date -u +%FT%TZ)"
