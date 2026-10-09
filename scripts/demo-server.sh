#!/bin/sh
# Boot Stockly against a throwaway SQLite database instead of the development
# server, so looking at the app can never read or write the MySQL data.
#
# Every variable is exported explicitly, which overrides anything in .env. The
# reset guard allows the migrate:fresh below because the connection is sqlite.
set -e

root="$(cd "$(dirname "$0")/.." && pwd)"
db="$root/storage/stockly-demo.sqlite"

# Storage settings that would otherwise reach for MySQL tables.
export DB_CONNECTION=sqlite
export DB_DATABASE="$db"
export CACHE_STORE=file
export QUEUE_CONNECTION=sync
export SESSION_DRIVER=file

rm -f "$db"
touch "$db"

php artisan migrate:fresh --seed --force "$@"

echo
echo "Serving Stockly on http://127.0.0.1:8000 against a throwaway database."
echo "This is a scratch copy: nothing here is your development data."
echo

exec php artisan serve --host=127.0.0.1 --port="${PORT:-8000}"
