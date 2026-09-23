FROM serversideup/php:8.3-fpm-nginx

USER root

WORKDIR /var/www/html

# gd required by barcode / mpdf / phpspreadsheet (composer platform check)
RUN install-php-extensions gd \
 && apt-get update \
 && apt-get install -y --no-install-recommends git unzip \
 && rm -rf /var/lib/apt/lists/*

COPY --chown=www-data:www-data . /var/www/html

# Fail the build if composer fails. Skip post-install artisan scripts (they need DB).
RUN set -eux; \
    test -f composer.json; \
    mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache; \
    chown -R www-data:www-data storage bootstrap/cache; \
    composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist --no-scripts; \
    composer dump-autoload --optimize --no-interaction; \
    test -f vendor/autoload.php

USER www-data

ENV SSL_MODE=off
ENV AUTORUN_ENABLED=false
ENV PHP_OPCACHE_ENABLE=1
