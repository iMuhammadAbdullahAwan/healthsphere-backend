#!/bin/bash
set -e

# If PORT env var is set by Render, update Apache to listen on it
if [ -n "${PORT}" ]; then
  sed -i "s/Listen 80/Listen ${PORT}/g" /etc/apache2/ports.conf || true
  sed -i "s/<VirtualHost \*:80>/<VirtualHost *:${PORT}>/g" /etc/apache2/sites-available/000-default.conf || true
fi

# Ensure writable permissions for CI4
chown -R www-data:www-data /var/www/html/writable || true

exec "$@"
#!/bin/bash
set -e

# If PORT env var is set by Render, update Apache to listen on it
if [ -n "${PORT}" ]; then
  sed -i "s/Listen 80/Listen ${PORT}/g" /etc/apache2/ports.conf || true
  sed -i "s/<VirtualHost \*:80>/<VirtualHost *:${PORT}>/g" /etc/apache2/sites-available/000-default.conf || true
fi

# Ensure writable permissions for CI4
chown -R www-data:www-data /var/www/html/writable || true

exec "$@"
#!/bin/bash
set -e

# If PORT env var is set by Render, update Apache to listen on it
if [ -n "${PORT}" ]; then
  sed -i "s/Listen 80/Listen ${PORT}/g" /etc/apache2/ports.conf || true
  sed -i "s/<VirtualHost \*:80>/<VirtualHost *:${PORT}>/g" /etc/apache2/sites-available/000-default.conf || true
fi

# Ensure writable permissions for CI4
chown -R www-data:www-data /var/www/html/writable || true

exec "$@"
