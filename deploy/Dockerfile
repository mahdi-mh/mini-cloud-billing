FROM php:8.4-apache-bookworm AS php-base
RUN apt-get update && apt-get install -y --no-install-recommends libicu-dev libzip-dev libsqlite3-dev unzip git \
    && docker-php-ext-install -j2 pdo_mysql pdo_sqlite intl zip bcmath pcntl opcache \
    && rm -rf /var/lib/apt/lists/* \
    && a2enmod rewrite headers
RUN apt-get update && apt-get install -y --no-install-recommends libonig-dev \
    && docker-php-ext-install mbstring && rm -rf /var/lib/apt/lists/*
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/000-default.conf \
    && printf '<Directory /var/www/html/public>\nAllowOverride All\nRequire all granted\n</Directory>\nServerTokens Prod\nServerSignature Off\n' > /etc/apache2/conf-available/minicloud.conf \
    && a2enconf minicloud
COPY deploy/php.ini /usr/local/etc/php/conf.d/minicloud.ini
COPY deploy/mpm_prefork.conf /etc/apache2/mods-available/mpm_prefork.conf
WORKDIR /var/www/html

FROM php-base AS dependencies
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY . .
RUN composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader

FROM node:22-bookworm-slim AS assets
WORKDIR /app
COPY --from=dependencies /var/www/html /app
RUN npm ci && npm run build

FROM php-base AS runtime
COPY --from=dependencies --chown=root:root /var/www/html /var/www/html
COPY --from=assets /app/public/build /var/www/html/public/build
RUN mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs \
    && chown -R www-data:www-data storage bootstrap/cache
