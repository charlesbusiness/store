#!/bin/sh

set -eu

if [ "${1:-}" = "--dry-run" ]; then
    shift
    # php artisan migrate --force
    php artisan stockly:migrate-tenancy --dry-run "$@"
    exit 0
fi

# php artisan migrate --force
php artisan stockly:migrate-tenancy "$@"
