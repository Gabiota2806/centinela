FROM composer:2.11.2 AS composer_bin
FROM node:26.13.0-slim AS node_bin

FROM php:8.5.12-cli

ARG UID=1000
ARG GID=1000

# Dependencias del sistema y librerías para extensiones PHP
RUN apt-get update && apt-get install -y --no-install-recommends \
    git \
    unzip \
    curl \
    libpq-dev \
    libzip-dev \
    libicu-dev \
    libonig-dev \
    libxml2-dev \
    libcurl4-openssl-dev \
    && docker-php-ext-install -j$(nproc) \
        pdo_pgsql \
        pgsql \
        mbstring \
        xml \
        zip \
        intl \
        bcmath \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Composer 2.11.2
COPY --from=composer_bin /usr/bin/composer /usr/bin/composer

# Node.js 26.13.0 + npm + npx
COPY --from=node_bin /usr/local/bin/node /usr/local/bin/node
COPY --from=node_bin /usr/local/lib/node_modules /usr/local/lib/node_modules
RUN ln -s /usr/local/lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm \
    && ln -s /usr/local/lib/node_modules/npm/bin/npx-cli.js /usr/local/bin/npx

# Usuario no-root con UID:GID del host (1000:1000)
RUN groupadd -g ${GID} centinela \
    && useradd -u ${UID} -g ${GID} -m -s /bin/bash centinela

WORKDIR /var/www/html

USER ${UID}:${GID}

EXPOSE 8000 5173

CMD ["sh", "-c", "trap 'rm -f public/hot; kill 0' INT TERM EXIT; rm -f public/hot && npm run dev -- --host 0.0.0.0 & php artisan serve --host=0.0.0.0 --port=8000 --no-reload & wait"]
