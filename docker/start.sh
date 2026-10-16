#!/bin/sh
set -e
# Exactly one MPM may be enabled; remove any others at runtime.
rm -f /etc/apache2/mods-enabled/mpm_event.* /etc/apache2/mods-enabled/mpm_worker.*
[ -e /etc/apache2/mods-enabled/mpm_prefork.load ] || a2enmod mpm_prefork >/dev/null

# Railway injects PORT; Apache must listen on it.
sed -ri "s/Listen 80/Listen ${PORT:-80}/" /etc/apache2/ports.conf
sed -ri "s/<VirtualHost \*:80>/<VirtualHost *:${PORT:-80}>/" /etc/apache2/sites-available/000-default.conf

mkdir -p "$DATA_DIR/uploads" "$DATA_DIR/sessions"
# Seeds only when the DB is empty. Never use --force here: it would wipe CMS edits.
php /var/www/html/bin/seed.php
chown -R www-data:www-data "$DATA_DIR"

exec apache2-foreground
