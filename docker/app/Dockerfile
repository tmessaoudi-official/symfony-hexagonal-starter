# ─── Build arguments ──────────────────────────────────────────────────────────
ARG PHP_VERSION=8.4
ARG COMPOSER_VERSION=2

# ═══════════════════════════════════════════════════════════════════════════════
# Stage 1: base — shared between dev, test, and prod
# ═══════════════════════════════════════════════════════════════════════════════
FROM php:${PHP_VERSION}-fpm-alpine AS base

# Install system dependencies required by PHP extensions.
# Pinned to Alpine package versions for reproducibility.
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
        opcache \
        pdo \
        pdo_pgsql \
        zip \
    && docker-php-ext-enable opcache \
    && rm -rf /var/cache/apk/*

# Install pcov for code coverage (much faster than Xdebug for pure coverage).
RUN pecl install pcov && docker-php-ext-enable pcov

# Copy PHP production ini.
COPY docker/app/php.ini /usr/local/etc/php/conf.d/app.ini

WORKDIR /app

# ═══════════════════════════════════════════════════════════════════════════════
# Stage 2: composer — install PHP dependencies
# ═══════════════════════════════════════════════════════════════════════════════
FROM composer:${COMPOSER_VERSION} AS composer-deps

WORKDIR /app

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

# Install Xdebug for interactive debugging (use pcov for coverage in CI).
RUN pecl install xdebug && docker-php-ext-enable xdebug

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY . .

# Install ALL dependencies including dev.
RUN composer install --prefer-dist --no-interaction

EXPOSE 9000

# ═══════════════════════════════════════════════════════════════════════════════
# Stage 4: test — used in CI for running the test suite
# ═══════════════════════════════════════════════════════════════════════════════
FROM base AS test

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
