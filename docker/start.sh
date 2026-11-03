#!/bin/sh
# Container start-up: prepare Laravel, update the database, start the
# background work, then serve the site. Settings come from environment
# variables (on Render: the service's Environment page), not a .env file.
set -e
cd /var/www/html

artisan() {
    runuser -u www-data -- php artisan "$@"
}

# Managed MySQL (e.g. Aiven) requires TLS: DB_CA_CERT holds the CA certificate text.
if [ -n "$DB_CA_CERT" ]; then
    printf '%s\n' "$DB_CA_CERT" > /tmp/db-ca.pem
    chmod 644 /tmp/db-ca.pem
    export MYSQL_ATTR_SSL_CA=/tmp/db-ca.pem
fi

artisan config:cache
artisan route:cache
artisan view:cache
artisan event:cache

artisan migrate --force
# Barangays, skills and the first administrator. Safe to repeat: existing rows are left as they are.
artisan db:seed --force

artisan jobauko:check || true

# Queue worker (announcements, opportunity alerts) and scheduler (daily
# reminders), restarted if they stop.
(while true; do artisan queue:work --sleep=3 --tries=1 --max-time=3600 || true; sleep 5; done) &
(while true; do artisan schedule:work || true; sleep 5; done) &

PORT="${PORT:-10000}"
sed -i "s/^Listen 80$/Listen ${PORT}/" /etc/apache2/ports.conf
sed -i "s/<VirtualHost \*:80>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-enabled/jobauko.conf

exec apache2-foreground
