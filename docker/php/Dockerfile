FROM php:8.3-cli

RUN apt-get update \
    && apt-get install -y --no-install-recommends libonig-dev libxml2-dev \
    && docker-php-ext-install -j"$(nproc)" dom mbstring xml xmlwriter \
    && pecl install pcov \
    && docker-php-ext-enable pcov \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app
