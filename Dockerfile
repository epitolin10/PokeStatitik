# syntax=docker/dockerfile:1

FROM composer:2 AS composer

FROM php:8.3-apache AS app

# System dependencies + PHP extensions required by the app
RUN apt-get update && apt-get install -y --no-install-recommends \
        libicu-dev \
        libzip-dev \
        libpq-dev \
        unzip \
        git \
    && docker-php-ext-configure intl \
    && docker-php-ext-install -j"$(nproc)" intl pdo_mysql pdo_pgsql zip opcache \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && { \
        echo 'opcache.enable=1'; \
        echo 'opcache.validate_timestamps=0'; \
        echo 'opcache.max_accelerated_files=20000'; \
        echo 'opcache.memory_consumption=192'; \
        echo 'realpath_cache_size=4096K'; \
        echo 'realpath_cache_ttl=600'; \
    } > "$PHP_INI_DIR/conf.d/zz-app.ini"

COPY --from=composer /usr/bin/composer /usr/bin/composer
COPY docker/apache/vhost.conf /etc/apache2/sites-available/000-default.conf

WORKDIR /var/www/html

ENV APP_ENV=prod \
    COMPOSER_ALLOW_SUPERUSER=1

# Install PHP dependencies first (better layer caching)
COPY composer.json composer.lock symfony.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --no-interaction --prefer-dist

# Copy the rest of the application
COPY . .

RUN composer dump-autoload --optimize --classmap-authoritative --no-dev

# Booting the kernel to build assets requires the container to compile, which
# needs *some* value for these env vars. The real values are supplied by
# Render at runtime and override these build-time placeholders.
ENV APP_SECRET=build_time_placeholder \
    DATABASE_URL="postgresql://app:app@127.0.0.1:5432/app?serverVersion=15.0.0&charset=utf8" \
    MAILER_DSN=null://null \
    MESSENGER_TRANSPORT_DSN=doctrine://default?auto_setup=0 \
    DEFAULT_URI=https://pokestatitik.onrender.com/ \
    APP_DEBUG=0

RUN { \
        echo 'APP_ENV=prod'; \
        echo 'APP_DEBUG=0'; \
        echo 'APP_SECRET=build_time_placeholder'; \
        echo 'DATABASE_URL=postgresql://app:app@127.0.0.1:5432/app?serverVersion=15.0.0&charset=utf8'; \
        echo 'MAILER_DSN=null://null'; \
        echo 'MESSENGER_TRANSPORT_DSN=doctrine://default?auto_setup=0'; \
        echo 'DEFAULT_URI=http://localhost'; \
    } > .env

# Pre-build front-end assets served by the AssetMapper
RUN php bin/console importmap:install --no-interaction \
    && php bin/console asset-map:compile --no-interaction

RUN mkdir -p var/cache var/log public/uploads \
    && chown -R www-data:www-data var public/uploads

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 80

ENTRYPOINT ["entrypoint.sh"]
CMD ["apache2-foreground"]
