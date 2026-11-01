#!/bin/sh
set -e

# Na primeira subida o ambiente se prepara sozinho, para que
# "docker compose up" seja o único passo para rodar o projeto.
[ -f .env ] || cp .env.example .env
[ -f vendor/autoload.php ] || composer install --no-interaction --prefer-dist --no-progress
grep -q '^APP_KEY=base64:' .env || php artisan key:generate --force

# O código vem montado do host: o php-fpm roda como www-data e precisa escrever log e cache.
# Em volume montado do Windows o chmod pode não ter efeito — e lá também não é necessário.
chmod -R ugo+rwX storage bootstrap/cache 2>/dev/null || true

php artisan migrate --force

exec "$@"
