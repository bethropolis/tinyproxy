# Use the official PHP 8.0 image as the base
FROM php:8.0-apache

# Set the working directory to /var/www/html
WORKDIR /var/www/html

# Copy your project files into the container
COPY . .

# Install Composer for managing dependencies
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    && rm -rf /var/lib/apt/lists/* \
    && curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Install dependencies using Composer
RUN composer install

# Enable Apache modules and configure the virtual host
RUN a2enmod rewrite
COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf

# Expose port 80
EXPOSE 80

# Start Apache
CMD ["apache2-foreground"]
