# ─── Build arguments ──────────────────────────────────────────────────────────
# 8.5: the newest PHP whose extensions (ext-amqp, pcov, phpredis) have stable releases that build.
# PHP 8.6 runs as a non-blocking CI job until all three ship stable 8.6-compatible releases.
ARG PHP_VERSION=8.5
ARG COMPOSER_VERSION=2
ARG PIE_VERSION=1.6.0

# PIE (PHP Installer for Extensions) is PECL's successor; the PHP 8.6 images no longer ship PECL.
# It is bind-mounted only while extensions are built, so it never lands in an image layer.
FROM ghcr.io/php/pie:${PIE_VERSION}-bin AS pie

# ═══════════════════════════════════════════════════════════════════════════════
# Stage 1: base — shared between dev, test, and prod
# ═══════════════════════════════════════════════════════════════════════════════
FROM php:${PHP_VERSION}-fpm-alpine AS base

# Install system dependencies required by PHP extensions.
# OPcache and PDO are compiled into the official PHP 8.5+ images (docker-php-ext-install
# opcache fails there). mbstring is bundled up to 8.5 but NOT in the 8.6 images, so it is
# built only when missing; it needs oniguruma.
RUN apk add --no-cache \
    bash \
    curl \
    git \
    icu-dev \
    libpq-dev \
    libzip-dev \
    oniguruma-dev \
    unzip \
    && docker-php-ext-install \
        intl \
        pdo_pgsql \
        zip \
    && (php -m | grep -qix mbstring || docker-php-ext-install mbstring) \
    && rm -rf /var/cache/apk/*

# AMQP extension for the RabbitMQ Messenger transport (every stage that runs workers) and
# phpredis for the Redis cache pools (config/packages/cache.yaml; prod cannot warm up without it).
RUN --mount=type=bind,from=pie,source=/pie,target=/usr/local/bin/pie \
    apk add --no-cache rabbitmq-c \
    && apk add --no-cache --virtual .ext-build-deps $PHPIZE_DEPS libtool linux-headers rabbitmq-c-dev \
    && pie install php-amqp/php-amqp \
    && pie install phpredis/phpredis \
    && apk del .ext-build-deps

# Copy PHP production ini.
COPY docker/app/php.ini /usr/local/etc/php/conf.d/app.ini

WORKDIR /app

# ═══════════════════════════════════════════════════════════════════════════════
# Stage 2: composer — install PHP dependencies
# ═══════════════════════════════════════════════════════════════════════════════
# Runs on the base image (not composer:2) so Composer's platform check sees the real PHP
# version and extensions (ext-amqp) the application will run with.
FROM base AS composer-deps

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY composer.json composer.lock ./

# --no-dev for the dependency graph only; dev deps are added in the dev/test stages.
RUN composer install \
    --no-dev \
    --prefer-dist \
    --no-interaction \
    --optimize-autoloader \
    --no-scripts

# ═══════════════════════════════════════════════════════════════════════════════
# Stage 3: dev — local development image with all dev tools
# ═══════════════════════════════════════════════════════════════════════════════
FROM base AS dev

# Xdebug for interactive debugging, pcov for coverage.
RUN --mount=type=bind,from=pie,source=/pie,target=/usr/local/bin/pie \
    apk add --no-cache --virtual .ext-build-deps $PHPIZE_DEPS libtool linux-headers \
    && pie install pecl/pcov \
    && pie install xdebug/xdebug \
    && apk del .ext-build-deps

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY . .

# Install ALL dependencies including dev.
RUN composer install --prefer-dist --no-interaction

EXPOSE 9000

# ═══════════════════════════════════════════════════════════════════════════════
# Stage 4: test — used in CI for running the test suite
# ═══════════════════════════════════════════════════════════════════════════════
FROM base AS test

# pcov for coverage (CI, mutation testing). Not in prod: coverage has no place there.
RUN --mount=type=bind,from=pie,source=/pie,target=/usr/local/bin/pie \
    apk add --no-cache --virtual .ext-build-deps $PHPIZE_DEPS libtool \
    && pie install pecl/pcov \
    && apk del .ext-build-deps

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY . .

RUN composer install --prefer-dist --no-interaction

ENV APP_ENV=test
ENV APP_DEBUG=1

EXPOSE 9000

# ═══════════════════════════════════════════════════════════════════════════════
# Stage 5: prod — lean production image
# ═══════════════════════════════════════════════════════════════════════════════
FROM base AS prod

# Copy production-only vendor dir from the composer stage.
COPY --from=composer-deps /app/vendor ./vendor

# Copy application source.
COPY . .

# Run Symfony warmup and asset install as part of the build.
RUN APP_ENV=prod APP_DEBUG=0 php bin/console cache:warmup \
    && chown -R www-data:www-data var

ENV APP_ENV=prod
ENV APP_DEBUG=0

EXPOSE 9000
