FROM php:8.2-apache

RUN apt-get update \
 && apt-get install -y --no-install-recommends \
        libpq-dev \
        libcurl4-openssl-dev \
 && rm -rf /var/lib/apt/lists/* \
 && docker-php-ext-install curl pdo pdo_pgsql opcache \
 && a2enmod rewrite headers

# Subir tamaño de upload (PDFs grandes + cert .p12)
RUN { \
        echo "upload_max_filesize = 64M"; \
        echo "post_max_size = 64M"; \
        echo "memory_limit = 256M"; \
        echo "max_execution_time = 180"; \
    } > /usr/local/etc/php/conf.d/firmaec.ini

COPY . /var/www/html/

RUN chown -R www-data:www-data /var/www/html

EXPOSE 80
