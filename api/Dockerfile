FROM php:8.4-cli-alpine

RUN apk add --no-cache \
        icu-dev \
        libxml2-dev \
        libzip-dev \
        oniguruma-dev \
        postgresql-dev \
        $PHPIZE_DEPS \
    && docker-php-ext-install \
        dom \
        intl \
        mbstring \
        pdo_pgsql \
        opcache \
        xml \
        xmlwriter \
        zip \
    && apk del $PHPIZE_DEPS

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY composer.json ./
RUN composer install --no-interaction --prefer-dist --no-scripts

COPY . .
RUN composer dump-autoload --optimize \
    && chmod +x docker-entrypoint.sh bin/console

EXPOSE 8080
ENTRYPOINT ["./docker-entrypoint.sh"]
