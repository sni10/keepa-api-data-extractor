FROM php:8.3-fpm

ARG APP_ENV
RUN echo "BUILDING FOR APP_ENV = ${APP_ENV}"

RUN apt-get update && apt-get install -y \
    libpng-dev \
    ncat \
    iproute2 \
    netcat-openbsd \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    librabbitmq-dev \
    zip \
    unzip \
    procps \
    net-tools \
    lsof \
    libfreetype6-dev \
    apt-transport-https \
    ca-certificates \
    gnupg \
    git \
    mc \
    curl \
    libpq-dev \
    rsync \
    supervisor \
    && docker-php-ext-install mbstring exif pcntl bcmath gd pdo pdo_pgsql zip sockets \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

USER root

RUN pecl install amqp && docker-php-ext-enable amqp

# Xdebug only for test environment
RUN if [ "$APP_ENV" = "test" ]; then \
        pecl install xdebug && docker-php-ext-enable xdebug; \
    fi

WORKDIR /var/www/keepa-api-data-extractor

# Copy all source code first
COPY . .

# Copy php.ini
COPY ./docker/configs-data/php.ini /usr/local/etc/php/conf.d/custom-php.ini

# Copy supervisord config
COPY ./docker/supervisor/supervisord.conf /etc/supervisord.conf
COPY ./docker/supervisor/*.conf /etc/supervisor/conf.d/

# Create .env file from example for build-time
RUN cp .env.example .env

# Install dependencies AFTER copying source
RUN if [ "$APP_ENV" = "test" ]; then \
        composer install --no-interaction --prefer-dist --optimize-autoloader; \
    else \
        composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader; \
    fi

# Setup permissions
RUN chown -R www-data:www-data /var/www \
    && chmod -R 775 /var/www \
    && mkdir -p /var/www/.composer/cache \
    && chown -R www-data:www-data /var/www/.composer

# Setup supervisor directories
RUN mkdir -p /var/run/supervisor /var/log/supervisor && \
    chown -R www-data:www-data /var/run/supervisor /var/log/supervisor && \
    chmod -R 775 /var/run/supervisor /var/log/supervisor

EXPOSE 9003
EXPOSE 9000

CMD ["sh", "-c", "if [ \"$APP_ENV\" = \"test\" ]; then php-fpm; else composer run-script serve; fi"]
