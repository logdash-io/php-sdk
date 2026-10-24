# logdash/php-sdk as Packagist serves it, VERSION or else the latest, never this checkout
FROM composer:2

WORKDIR /app

ARG VERSION=
RUN composer require --no-interaction --no-progress "logdash/php-sdk${VERSION:+:$VERSION}"

COPY check.php .

CMD ["php", "check.php"]
