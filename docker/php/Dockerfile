FROM php:8.4-fpm-alpine

# pdo_mysql para a aplicação; zip para o Composer baixar pacotes sem depender de git.
# SQLite (usado pelos testes) já vem compilado na imagem oficial.
RUN apk add --no-cache libzip \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS libzip-dev \
    && docker-php-ext-install pdo_mysql zip \
    && apk del .build-deps

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
ENV COMPOSER_ALLOW_SUPERUSER=1

COPY entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

WORKDIR /var/www/html

ENTRYPOINT ["entrypoint"]
CMD ["php-fpm"]
