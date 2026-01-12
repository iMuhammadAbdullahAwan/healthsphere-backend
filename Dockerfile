FROM php:8.2-apache

ENV APACHE_DOCUMENT_ROOT /var/www/html/public

RUN apt-get update && apt-get install -y \
    git unzip libzip-dev libpng-dev libicu-dev libonig-dev zlib1g-dev libxml2-dev libjpeg-dev && \
    docker-php-ext-configure gd --with-jpeg && \
    docker-php-ext-install pdo pdo_mysql mbstring zip exif pcntl intl xml opcache gd && \
    a2enmod rewrite

# Install composer binary from official composer image
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Install PHP dependencies first (cached layer)
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --optimize-autoloader --prefer-dist || true

# Copy application
COPY . .

# Ensure writable dirs are owned by www-data
RUN chown -R www-data:www-data /var/www/html/writable || true && \
    chmod -R 755 /var/www/html/writable || true

# Make Apache serve the CodeIgniter `public` directory
RUN sed -ri -e 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/000-default.conf /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Expose a sensible default; Render will set $PORT at runtime and the entrypoint will adapt Apache
EXPOSE 8080

COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]
CMD ["apache2-foreground"]
FROM php:8.2-apache

ENV APACHE_DOCUMENT_ROOT /var/www/html/public

RUN apt-get update && apt-get install -y \
    git unzip libzip-dev libpng-dev libicu-dev libonig-dev zlib1g-dev libxml2-dev libjpeg-dev && \
    docker-php-ext-configure gd --with-jpeg && \
    docker-php-ext-install pdo pdo_mysql mbstring zip exif pcntl intl xml opcache gd && \
    a2enmod rewrite

# Install composer binary from official composer image
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Install PHP dependencies first (cached layer)
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --optimize-autoloader --prefer-dist || true

# Copy application
COPY . .

# Ensure writable dirs are owned by www-data
RUN chown -R www-data:www-data /var/www/html/writable || true && \
    chmod -R 755 /var/www/html/writable || true

# Make Apache serve the CodeIgniter `public` directory
RUN sed -ri -e 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/000-default.conf /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Expose a sensible default; Render will set $PORT at runtime and the entrypoint will adapt Apache
EXPOSE 8080

COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]
CMD ["apache2-foreground"]
FROM php:8.2-apache

ENV APACHE_DOCUMENT_ROOT /var/www/html/public

RUN apt-get update && apt-get install -y \
    git unzip libzip-dev libpng-dev libicu-dev libonig-dev zlib1g-dev libxml2-dev libjpeg-dev && \
    docker-php-ext-configure gd --with-jpeg && \
    docker-php-ext-install pdo pdo_mysql mbstring zip exif pcntl intl xml opcache gd && \
    a2enmod rewrite

# Install composer binary from official composer image
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Install PHP dependencies first (cached layer)
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --optimize-autoloader --prefer-dist || true

# Copy application
COPY . .

# Ensure writable dirs are owned by www-data
RUN chown -R www-data:www-data /var/www/html/writable || true && \
    chmod -R 755 /var/www/html/writable || true

# Make Apache serve the CodeIgniter `public` directory
RUN sed -ri -e 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/000-default.conf /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Expose a sensible default; Render will set $PORT at runtime and the entrypoint will adapt Apache
EXPOSE 8080

COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]
CMD ["apache2-foreground"]
