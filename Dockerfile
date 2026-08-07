# syntax=docker/dockerfile:1

# =============================================================================
# صَبّة — production image
#
# One image containing the whole product: Laravel serves the built React app, so
# there is a single process, a single origin and no CORS.
#
# Portable on purpose — Railway, Render and Fly.io all build a Dockerfile, so
# this is not tied to one host.
# =============================================================================


# -----------------------------------------------------------------------------
# Stage 1 — build the frontend
#
# Node is only needed here. It is not carried into the final image, which keeps
# the runtime small and removes the whole npm dependency tree from production.
# -----------------------------------------------------------------------------
FROM node:22-alpine AS frontend

WORKDIR /build

# Copy manifests first so this layer is cached unless dependencies change.
COPY frontend/package.json frontend/package-lock.json ./
RUN npm ci

COPY frontend/ ./
# vite.config.js writes to ../backend/public/app, so give it that path to write to.
RUN mkdir -p /backend/public && npm run build


# -----------------------------------------------------------------------------
# Stage 2 — runtime
#
# FrankenPHP is a real application server (unlike `php artisan serve`, which is
# single-threaded and explicitly not for production). It serves from
# /app/public, which is exactly Laravel's document root.
# -----------------------------------------------------------------------------
FROM dunglas/frankenphp:1-php8.4

WORKDIR /app

# pdo_sqlite for the brew log; intl and zip are expected by parts of the
# framework. install-php-extensions ships with this image.
RUN install-php-extensions pdo_sqlite intl zip opcache

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Dependencies first, again for layer caching. --no-scripts because artisan
# cannot run before the application code is present.
COPY backend/composer.json backend/composer.lock ./
RUN composer install \
      --no-dev \
      --no-scripts \
      --no-interaction \
      --prefer-dist \
      --optimize-autoloader

COPY backend/ ./

# The frontend build, dropped into Laravel's public directory.
COPY --from=frontend /backend/public/app ./public/app

RUN composer dump-autoload --optimize --classmap-authoritative \
    && mkdir -p storage/framework/{cache,sessions,views} storage/logs database \
    && chown -R www-data:www-data storage bootstrap/cache database

# Caddy needs to know it is behind the host's TLS terminator; :8080 serves plain
# HTTP and lets the platform handle certificates.
ENV SERVER_NAME=:8080
EXPOSE 8080

# Migrations run at boot rather than at build time: the database does not exist
# during the build, and a fresh volume needs its schema on first start.
COPY docker-entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["frankenphp", "php-server", "--listen", ":8080", "--root", "/app/public"]
