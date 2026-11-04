# Test runner image for older PHP versions: tests/run.sh --php=7.4
#
# Alpine variant: the SQLite drop-in used by the tests needs SQLite >= 3.37;
# the Debian-based php:7.4 image only has 3.34.
ARG PHP_VERSION=7.4
FROM php:${PHP_VERSION}-cli-alpine

RUN apk add --no-cache bash curl unzip python3 openssl less procps sqlite-libs libpng libjpeg-turbo freetype libzip \
	&& apk add --no-cache --virtual .build libpng-dev libjpeg-turbo-dev freetype-dev libzip-dev \
	&& docker-php-ext-configure gd --with-jpeg --with-freetype \
	&& docker-php-ext-install -j"$(nproc)" gd mysqli zip \
	&& apk del .build \
	&& curl -fsSL -o /usr/local/bin/wp https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar \
	&& chmod +x /usr/local/bin/wp \
	&& echo "memory_limit=512M" > /usr/local/etc/php/conf.d/tests.ini

ENV WP_CLI_ALLOW_ROOT=1
WORKDIR /work
