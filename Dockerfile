# Inspired from Dockerfile made by Kenneth Trecy Tobias for Kotovaulo server (variant A)
# Adapted by Kenneth Trecy Tobias for Peratorakka server

# Other links that influenced this file:
# - `initial_influence`. https://dev.to/veevidify/docker-compose-up-your-entire-laravel-apache-mysql-development-environment-45ea
# - `libonig-dev`. https://www.limstash.com/en/articles/202002/1539

FROM php:8.2-apache AS base

# 1. Install necessary packages.
RUN apt-get update && apt-get install -y \
	curl \
	g++ \
	git \
	libfreetype6-dev \
	libmcrypt-dev \
	sudo \
	unzip \
	zip

# 2. Apache configs + document root.
RUN echo "ServerName server.kotovaulo.local" >> /etc/apache2/apache2.conf

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# 3. mod_rewrite for URL rewrite and mod_headers for .htaccess extra headers like Access-Control-Allow-Origin-
RUN a2enmod rewrite headers

# 4. Copy base PHP config from development.
RUN cp "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"

# 5. Add PHP extensions.
RUN docker-php-ext-install bcmath

RUN sudo apt-get update
RUN apt-get install -y libbz2-dev
RUN docker-php-ext-install bz2

RUN docker-php-ext-install calendar
# RUN docker-php-ext-install ctype
# RUN docker-php-ext-install curl
# RUN docker-php-ext-install dba
# RUN docker-php-ext-install dl_test
# RUN docker-php-ext-install dom
# RUN docker-php-ext-install enchant
# RUN docker-php-ext-install exif
# RUN docker-php-ext-install ffi
# RUN docker-php-ext-install fileinfo
# RUN docker-php-ext-install filter
# RUN docker-php-ext-install ftp

RUN sudo apt-get update
RUN apt-get install -y libjpeg-dev libpng-dev
RUN docker-php-ext-install gd

# RUN docker-php-ext-install gettext
# RUN docker-php-ext-install gmp
# RUN docker-php-ext-install hash
RUN docker-php-ext-install iconv
# RUN docker-php-ext-install imap

RUN sudo apt-get update
RUN apt-get install -y libicu-dev
RUN docker-php-ext-install intl

# RUN docker-php-ext-install json
# RUN docker-php-ext-install ldap

RUN sudo apt-get update
RUN apt-get install -y libonig-dev
RUN docker-php-ext-install mbstring

# RUN docker-php-ext-install mysqli
# RUN docker-php-ext-install oci8
# RUN docker-php-ext-install odbc
RUN docker-php-ext-install opcache
# RUN docker-php-ext-install pcntl
# RUN docker-php-ext-install pdo
# RUN docker-php-ext-install pdo_dblib
# RUN docker-php-ext-install pdo_firebird
# RUN docker-php-ext-install pdo_mysql
# RUN docker-php-ext-install pdo_oci
# RUN docker-php-ext-install pdo_odbc

RUN sudo apt-get update
RUN apt-get install -y libpq-dev
RUN docker-php-ext-install pdo_pgsql

# RUN docker-php-ext-install pdo_sqlite

RUN docker-php-ext-install pgsql

# RUN docker-php-ext-install phar
# RUN docker-php-ext-install posix
# RUN docker-php-ext-install pspell
# RUN docker-php-ext-install random

# RUN sudo apt-get update
# RUN apt-get install -y libedit-dev libedit2 libreadline-dev
# RUN docker-php-ext-install readline # errors here

# RUN docker-php-ext-install reflection
# RUN docker-php-ext-install session
# RUN docker-php-ext-install shmop
# RUN docker-php-ext-install simplexml
# RUN docker-php-ext-install snmp
# RUN docker-php-ext-install soap
# RUN docker-php-ext-install sockets
# RUN docker-php-ext-install sodium
# RUN docker-php-ext-install spl
# RUN docker-php-ext-install standard
# RUN docker-php-ext-install sysvmsg
# RUN docker-php-ext-install sysvsem
# RUN docker-php-ext-install sysvshm
# RUN docker-php-ext-install tidy
# RUN docker-php-ext-install tokenizer
# RUN docker-php-ext-install xml
# RUN docker-php-ext-install xmlreader
# RUN docker-php-ext-install xmlwriter
# RUN docker-php-ext-install xsl
# RUN docker-php-ext-install zend_test

RUN sudo apt-get update
RUN apt-get install -y libzip-dev
RUN docker-php-ext-install zip

# 6. Clear APT cache.
RUN rm -rf /var/lib/apt/lists/*

# 7. Install composer.
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer


##########


FROM base as development


##########


FROM base as production

# 1. Copy base PHP config from production.
RUN rm "$PHP_INI_DIR/php.ini-development"
RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

# 2. Copy all configuration files
COPY . /var/www/html/
RUN sudo chmod -R a+rw /var/www/html

RUN /usr/bin/composer install
RUN sudo chmod -R a+rw /var/www/html/vendor
RUN /usr/bin/composer run migrate:all
