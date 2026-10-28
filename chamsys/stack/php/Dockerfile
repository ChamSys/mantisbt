# PHP-FPM runtime for developing the MantisBT fork. The application code is not baked in;
# the repository is bind-mounted (see ../docker-compose.yml).
FROM php:8.5-fpm

RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends \
        libzip-dev libpng-dev libjpeg-dev libfreetype6-dev libonig-dev libicu-dev \
        libxml2-dev unzip git curl; \
    rm -rf /var/lib/apt/lists/*

# Extensions MantisBT requires or recommends (curl is already in the base image).
RUN set -eux; \
    docker-php-ext-configure gd --with-freetype --with-jpeg; \
    docker-php-ext-install gd mysqli mbstring zip intl soap

RUN curl -sS https://getcomposer.org/installer | php -- \
      --install-dir=/usr/local/bin --filename=composer

# Development settings: errors go to the log (php-fpm's stderr, see `stack.sh logs`).
RUN { \
      echo 'opcache.enable=1'; \
      echo 'opcache.validate_timestamps=1'; \
      echo 'opcache.revalidate_freq=0'; \
      echo 'display_errors=off'; \
      echo 'log_errors=on'; \
      echo 'date.timezone=Europe/London'; \
      echo 'upload_max_filesize=16M'; \
      echo 'post_max_size=16M'; \
    } > /usr/local/etc/php/conf.d/zz-dev.ini

WORKDIR /var/www/html
