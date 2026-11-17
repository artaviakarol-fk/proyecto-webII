FROM php:8.4-cli

RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip libpq-dev libzip-dev libicu-dev libpng-dev libjpeg-dev libwebp-dev \
    && docker-php-ext-configure gd --with-jpeg --with-webp \
    && docker-php-ext-install pdo_pgsql zip intl gd \
    && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-install opcache
COPY docker/opcache.ini /usr/local/etc/php/conf.d/opcache-local.ini

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 8000

# Varios procesos atienden a la vez, para que una petición lenta no deje esperando a las demás.
ENV PHP_CLI_SERVER_WORKERS=4

ENTRYPOINT ["entrypoint.sh"]
# --no-reload keeps the DB_* variables from docker-compose: without it, artisan serve
# drops every variable also defined in .env and the API cannot reach the database.
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000", "--no-reload"]
