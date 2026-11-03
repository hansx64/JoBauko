# JoBauko container: Apache + PHP 8.4, with the queue worker and scheduler
# running alongside (see docker/start.sh). Built and run by Render; works
# with any Docker host. See docs/DEPLOYMENT-RENDER.md.

FROM php:8.4-apache AS base

COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions pdo_mysql intl gd zip bcmath exif opcache \
    && a2enmod rewrite headers \
    && rm -f /etc/apache2/sites-enabled/000-default.conf

COPY docker/php.ini /usr/local/etc/php/conf.d/zz-jobauko.ini
COPY docker/apache.conf /etc/apache2/sites-enabled/jobauko.conf
COPY docker/mpm.conf /etc/apache2/conf-enabled/zz-jobauko-mpm.conf

WORKDIR /var/www/html

# PHP packages. Installed before the frontend build, which reads Ziggy from vendor/.
FROM base AS vendor
COPY --from=composer:2.8 /usr/bin/composer /usr/local/bin/composer
ENV COMPOSER_ALLOW_SUPERUSER=1
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction
COPY . .
RUN composer dump-autoload --optimize --no-dev

FROM node:22-bookworm-slim AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY --from=vendor /var/www/html /app
RUN npm run build

FROM base
COPY --from=vendor --chown=www-data:www-data /var/www/html /var/www/html
COPY --from=assets --chown=www-data:www-data /app/public/build /var/www/html/public/build
RUN mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs storage/app/private bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod +x docker/start.sh

CMD ["/var/www/html/docker/start.sh"]
