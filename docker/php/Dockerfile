FROM php:8.4-fpm-alpine

RUN apk add --no-cache $PHPIZE_DEPS icu-dev libzip-dev linux-headers \
    && docker-php-ext-install pdo_mysql bcmath intl pcntl zip \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del $PHPIZE_DEPS

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint
# Strip CRLF in case the repo was checked out on Windows, and make it executable.
RUN sed -i 's/\r$//' /usr/local/bin/entrypoint && chmod +x /usr/local/bin/entrypoint

WORKDIR /var/www/html
ENTRYPOINT ["entrypoint"]
CMD ["php-fpm"]
