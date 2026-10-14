#!/bin/sh
set -e

# Fallback APP_KEY if not configured in Vercel Environment Variables
if [ -z "$APP_KEY" ]; then
    export APP_KEY="base64:V2PwITyrOEz3JZNdmV0Qds++ind7cvkbipHZmi+5yzk="
fi

# Automatically run migrations on startup
if [ "$DB_CONNECTION" = "pgsql" ] || [ -n "$DATABASE_URL" ]; then
    echo "Running migrations on PostgreSQL/Supabase..."
    php artisan migrate --force || true
    php artisan db:seed --force || true
else
    echo "Using SQLite database..."
    mkdir -p /app/database
    touch /app/database/database.sqlite
    php artisan migrate --force || true
    php artisan db:seed --force || true
fi

exec frankenphp run --config /etc/frankenphp/Caddyfile
