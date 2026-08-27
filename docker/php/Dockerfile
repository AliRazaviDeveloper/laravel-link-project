### ---------------------------------------------------------------------------
### Stage 1 — base runtime shared by every PHP container (fpm, worker, cron)
### ---------------------------------------------------------------------------
FROM php:8.4-fpm-alpine AS base

RUN apk add --no-cache \
        bash \
        curl \
        git \
        icu-data-full \
        icu-libs \
        postgresql-client \
        tini \
        unzip \
    && apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS \
        icu-dev \
        linux-headers \
        openssl-dev \
        postgresql-dev \
        zlib-dev \
    && docker-php-ext-configure pcntl --enable-pcntl \
    && docker-php-ext-install -j"$(nproc)" \
        intl \
        opcache \
        pcntl \
        pdo_pgsql \
        sockets \
    && pecl install mongodb redis \
    && docker-php-ext-enable mongodb redis \
    && pecl clear-cache \
    && apk del .build-deps \
    && rm -rf /tmp/pear

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

COPY docker/php/conf.d/ /usr/local/etc/php/conf.d/
COPY docker/php/php-fpm.d/pool.conf /usr/local/etc/php-fpm.d/zz-pool.conf

# Match the host user so bind-mounted files stay writable in development.
#
# Both ids may already exist in the base image — a macOS host's GID 20 collides with
# Alpine's `dialout`, for instance — so an existing group is reused rather than
# treated as an error.
ARG UID=1000
ARG GID=1000
RUN set -eu; \
    if getent group "$GID" >/dev/null; then \
        group="$(getent group "$GID" | cut -d: -f1)"; \
    else \
        addgroup -g "$GID" -S app; \
        group=app; \
    fi; \
    if ! getent passwd "$UID" >/dev/null; then \
        adduser -u "$UID" -S -G "$group" -h /var/www -s /bin/bash app; \
    fi; \
    user="$(getent passwd "$UID" | cut -d: -f1)"; \
    mkdir -p /var/www/html; \
    chown -R "$user:$group" /var/www

WORKDIR /var/www/html

### ---------------------------------------------------------------------------
### Stage 2 — development image: xdebug available, sources come from a volume
### ---------------------------------------------------------------------------
FROM base AS development
ARG UID=1000
ARG GID=1000

RUN apk add --no-cache --virtual .xdebug-deps $PHPIZE_DEPS linux-headers \
    && pecl install xdebug \
    && docker-php-ext-enable xdebug \
    && pecl clear-cache \
    && apk del .xdebug-deps \
    && rm -rf /tmp/pear

COPY docker/php/conf.d-dev/ /usr/local/etc/php/conf.d/
COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

USER ${UID}:${GID}
ENTRYPOINT ["/sbin/tini", "--", "/usr/local/bin/entrypoint"]
CMD ["php-fpm"]

### ---------------------------------------------------------------------------
### Stage 3 — composer dependencies resolved in isolation for layer caching
### ---------------------------------------------------------------------------
FROM base AS vendor

COPY composer.json composer.lock ./
RUN composer install \
        --no-dev \
        --no-scripts \
        --no-autoloader \
        --prefer-dist \
        --no-interaction

### ---------------------------------------------------------------------------
### Stage 4 — production image: code baked in, opcache preloaded, no composer
### ---------------------------------------------------------------------------
FROM base AS production
ARG UID=1000
ARG GID=1000

ENV APP_ENV=production \
    APP_DEBUG=false

COPY --from=vendor --chown=${UID}:${GID} /var/www/html/vendor ./vendor
COPY --chown=${UID}:${GID} . .

RUN composer dump-autoload --classmap-authoritative --no-dev --no-interaction \
    && php artisan package:discover --ansi \
    && rm -rf /usr/local/bin/composer \
    && chmod -R ug+rw storage bootstrap/cache

COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

USER ${UID}:${GID}
ENTRYPOINT ["/sbin/tini", "--", "/usr/local/bin/entrypoint"]
CMD ["php-fpm"]
