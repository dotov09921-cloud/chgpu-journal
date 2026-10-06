FROM php:8.2-apache

RUN apt-get update \
    && DEBIAN_FRONTEND=noninteractive apt-get install -y --no-install-recommends \
       default-mysql-client \
       libzip-dev \
       zip \
       unzip \
       curl \
       ca-certificates \
       msmtp \
       msmtp-mta \
    && docker-php-ext-install pdo_mysql zip \
    && a2enmod rewrite headers expires \
    && sed -ri 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf \
    && rm -rf /var/lib/apt/lists/*

COPY docker/php-dev.ini /usr/local/etc/php/conf.d/zz-chgpu-dev.ini
COPY docker/msmtprc /etc/msmtprc
COPY docker/entrypoint.sh /usr/local/bin/chgpu-entrypoint

RUN chmod 0644 /etc/msmtprc \
    && chmod +x /usr/local/bin/chgpu-entrypoint

WORKDIR /var/www/html

ENTRYPOINT ["chgpu-entrypoint"]
CMD ["apache2-foreground"]
