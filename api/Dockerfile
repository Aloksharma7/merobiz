FROM php:8.4-cli-alpine

RUN apk add --no-cache \
      curl-dev \
      icu-dev \
      libxml2-dev \
      libzip-dev \
      oniguruma-dev \
      unzip \
      zip \
    && docker-php-ext-install bcmath curl dom intl mbstring opcache pdo_mysql xml zip \
    && rm -rf /tmp/*

COPY --from=composer:2.8 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
COPY composer.json ./
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-scripts
COPY . .
RUN composer dump-autoload --no-dev --optimize --no-interaction \
    && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

COPY docker/entrypoint.sh /usr/local/bin/merobiz-entrypoint
RUN chmod +x /usr/local/bin/merobiz-entrypoint

EXPOSE 8000
ENTRYPOINT ["merobiz-entrypoint"]
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
