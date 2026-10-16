FROM php:8.4-apache

RUN a2dismod -f mpm_event mpm_worker >/dev/null 2>&1 || true; a2enmod mpm_prefork rewrite headers remoteip \
 && apt-get update && apt-get install -y --no-install-recommends unzip git \
 && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri "s#/var/www/html#${APACHE_DOCUMENT_ROOT}#g" /etc/apache2/sites-available/*.conf /etc/apache2/apache2.conf \
 && sed -ri 's#AllowOverride None#AllowOverride All#' /etc/apache2/apache2.conf

WORKDIR /var/www/html
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-scripts
COPY . .
RUN composer dump-autoload --no-dev --optimize

COPY docker/start.sh /usr/local/bin/start.sh
RUN chmod +x /usr/local/bin/start.sh

# Railway Volume mounts here; DB, uploads and sessions live under it.
ENV DATA_DIR=/data APP_ENV=production
CMD ["start.sh"]
