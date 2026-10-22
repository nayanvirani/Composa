# Composa — one image for every Railway service. ROLE selects what the container runs:
#   web (default)  FrankenPHP serving the app, website, admin and App Proxy
#   worker         Horizon queue workers
#   scheduler      Laravel scheduler (php artisan schedule:work)
ARG PHP_VERSION=8.5

# ---- front-end assets: merchant app (Vite) + storefront bundles (esbuild) ------------------
FROM node:22-bookworm-slim AS assets
WORKDIR /app
COPY package.json package-lock.json .npmrc ./
RUN npm ci
COPY vite.config.js ./
COPY resources ./resources
COPY engine ./engine
COPY storefront ./storefront
COPY extensions ./extensions
RUN npx vite build && node storefront/build.mjs

# ---- PHP dependencies ----------------------------------------------------------------------
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --ignore-platform-reqs

# ---- runtime ---------------------------------------------------------------------------------
FROM dunglas/frankenphp:1-php${PHP_VERSION}-bookworm AS app
ENV APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr \
    SERVER_NAME=":8080" \
    PORT=8080

RUN install-php-extensions pdo_pgsql pgsql intl gd exif zip bcmath pcntl opcache redis \
 && apt-get update && apt-get install -y --no-install-recommends fonts-dejavu-core && rm -rf /var/lib/apt/lists/* \
 && cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY docker/php.ini $PHP_INI_DIR/conf.d/zz-composa.ini
COPY docker/Caddyfile /etc/caddy/Caddyfile

WORKDIR /app
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY --from=vendor /app/vendor ./vendor
COPY . .
COPY --from=assets /app/public/build ./public/build
COPY --from=assets /app/extensions/composa-theme/assets ./extensions/composa-theme/assets

RUN composer dump-autoload --optimize --classmap-authoritative --no-dev \
 && php artisan package:discover --ansi \
 && php artisan filament:assets \
 && mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache \
 && chown -R www-data:www-data storage bootstrap/cache /data/caddy /config/caddy \
 && chmod +x docker/start-container.sh

USER www-data
EXPOSE 8080
# Health check: configure GET /up on the Railway web service only (workers serve no HTTP).
CMD ["docker/start-container.sh"]
