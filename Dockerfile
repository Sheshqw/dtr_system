FROM php:8.4-fpm-alpine

# System dependencies
RUN apk add --no-cache \
    nginx \
    supervisor \
    curl \
    ca-certificates \
    nodejs \
    npm \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    libzip-dev \
    oniguruma-dev \
    icu-dev \
    linux-headers \
    mysql-client

# PHP extensions — sockets is for the ZKTeco library (rats/zkteco), pcntl
# lets Reverb shut down cleanly when the container restarts.
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
 && docker-php-ext-install -j$(nproc) \
    pdo pdo_mysql mbstring zip gd bcmath opcache intl sockets pcntl

# PHP production settings
RUN { \
    echo "opcache.enable=1"; \
    echo "opcache.memory_consumption=128"; \
    echo "opcache.interned_strings_buffer=32"; \
    echo "opcache.max_accelerated_files=20000"; \
    echo "opcache.revalidate_freq=0"; \
    echo "opcache.validate_timestamps=0"; \
} > /usr/local/etc/php/conf.d/opcache.ini \
 && { \
    echo "upload_max_filesize=20M"; \
    echo "post_max_size=20M"; \
    echo "memory_limit=256M"; \
    echo "max_execution_time=120"; \
    echo "date.timezone=Asia/Manila"; \
} > /usr/local/etc/php/conf.d/limits.ini

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Copy application source
COPY . .

# Remove any local .env so it does not leak into the image
RUN rm -f .env

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Build the Vue front end. VITE_* values are baked into the JS at build time,
# so they have to arrive as build args — Railway passes every service
# variable whose name matches an ARG below.
ARG VITE_REVERB_APP_KEY
ARG VITE_REVERB_HOST
ARG VITE_REVERB_PORT=443
ARG VITE_REVERB_SCHEME=https
RUN npm ci \
 && npm run build \
 && rm -rf node_modules

# Permissions
# nginx-client-body and nginx-fastcgi are where nginx spills request/response
# buffers too big to hold in memory (see docker/nginx.conf) — nginx does not
# create these itself.
RUN chown -R www-data:www-data /var/www/html \
 && chmod -R 755 /var/www/html/storage \
 && chmod -R 755 /var/www/html/bootstrap/cache \
 && mkdir -p /var/log/nginx /var/log/supervisor /run/nginx \
    /tmp/nginx-client-body /tmp/nginx-fastcgi \
 && chown -R www-data:www-data /tmp/nginx-client-body /tmp/nginx-fastcgi

# Copy runtime config files
COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/php-fpm-logging.conf /usr/local/etc/php-fpm.d/zz-logging.conf
COPY docker/start.sh /start.sh
RUN chmod +x /start.sh

EXPOSE 8000

CMD ["/start.sh"]
