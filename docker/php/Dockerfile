# ── Node/Vite asset build ────────────────────────────────────
FROM node:20-alpine AS node-builder
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --ignore-scripts
COPY vite.config.js postcss.config.js ./
COPY resources/ ./resources/
RUN npm run build

# ── PHP application ──────────────────────────────────────────
FROM php:8.3-fpm-alpine AS base

RUN apk add --no-cache \
    linux-headers \
    $PHPIZE_DEPS \
    libpng-dev \
    libjpeg-turbo-dev \
    libwebp-dev \
    freetype-dev \
    libzip-dev \
    icu-dev \
    oniguruma-dev \
    curl \
    su-exec \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql \
        mbstring \
        exif \
        pcntl \
        bcmath \
        gd \
        zip \
        intl \
        opcache \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del $PHPIZE_DEPS linux-headers

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY docker/php/php.ini /usr/local/etc/php/conf.d/app.ini
COPY docker/php/www.conf /usr/local/etc/php-fpm.d/www.conf

WORKDIR /var/www/html

COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-scripts

COPY . .

COPY --from=node-builder /app/public/build ./public/build

RUN composer run-script post-autoload-dump 2>/dev/null || true \
    && chown -R www-data:www-data /var/www/html/storage \
    && chown -R www-data:www-data /var/www/html/bootstrap/cache \
    && chmod -R 775 /var/www/html/storage \
    && chmod -R 775 /var/www/html/bootstrap/cache \
    && chmod +x /var/www/html/docker/php/entrypoint.sh \
    && mkdir -p /var/log/php-fpm \
    && chown www-data:www-data /var/log/php-fpm

EXPOSE 9000

HEALTHCHECK --interval=10s --timeout=5s --retries=3 \
    CMD php-fpm -t 2>&1 | grep -q "configuration file" && echo OK || exit 1

CMD ["php-fpm"]

# ── Production target (used by docker-compose.prod.yml) ──────
FROM base AS production
ENV APP_ENV=production \
    APP_DEBUG=false \
    LOG_LEVEL=warning
