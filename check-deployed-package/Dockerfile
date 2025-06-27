FROM php:8.3-cli

WORKDIR /app

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copy composer.json for the test application
COPY check-deployed-package/composer.json /app/
COPY check-deployed-package/src /app/src/

# Install the published logdash package from Packagist
RUN composer install --no-dev --optimize-autoloader

# Run the application
CMD ["php", "src/main.php"] 
