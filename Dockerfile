# Use a modern PHP version
FROM php:8.3-apache

# Enable mod_rewrite
RUN a2enmod rewrite

# Change DocumentRoot to public directory
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Set the working directory
WORKDIR /var/www/html

# Install dependencies and extensions
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libzip-dev \
    && docker-php-ext-install zip \
    && pecl install apcu \
    && docker-php-ext-enable apcu \
    && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copy project files
COPY . .

# Install PHP dependencies
RUN composer install --optimize-autoloader

# Ensure proper permissions for the var directory
RUN mkdir -p var/cache/rate_limit var/log \
    && chown -R www-data:www-data var \
    && chmod -R 775 var

# Expose port 80
EXPOSE 80

# Start Apache
CMD ["apache2-foreground"]
