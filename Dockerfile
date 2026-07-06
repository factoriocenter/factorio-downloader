FROM php:8.2-apache

# Install required system dependencies
RUN apt-get update && apt-get install -y \
    curl \
    git \
    unzip \
    && rm -rf /var/lib/apt/lists/*

# Install Composer from the official image
COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer

# Set working directory to Apache document root
WORKDIR /var/www/html

# Copy project files into the container
COPY . /var/www/html

# Create a directory for certificates and set proper permissions
RUN mkdir -p /var/www/html/certs && chown -R www-data:www-data /var/www/html/certs

# Declare a volume for certificates so they persist across container restarts
VOLUME ["/var/www/html/certs"]

# Run Composer install to generate vendor/autoload.php
RUN composer install --no-dev --optimize-autoloader

# Set proper permissions for Apache
RUN chown -R www-data:www-data /var/www/html && chmod -R 755 /var/www/html

# Enable Apache mod_rewrite and mod_headers (required by the security headers
# in .htaccess), and allow .htaccess to actually take effect: the base image's
# default vhost ships with "AllowOverride None", which would otherwise make the
# whole .htaccess (blocking .env, .git, vendor/, etc.) silently inert.
RUN a2enmod rewrite headers \
    && printf '<Directory /var/www/html>\n    AllowOverride All\n</Directory>\n' \
       > /etc/apache2/conf-available/htaccess-allow.conf \
    && a2enconf htaccess-allow

# Expose port 80
EXPOSE 80

# Start Apache in the foreground
CMD ["apache2-foreground"]
