FROM php:8.3-cli

WORKDIR /app

# Install system dependencies needed for PHP extensions and Composer
RUN apt-get update && apt-get install -y \
  git \
  unzip \
  zip \
  libzip-dev \
  pkg-config \
  && docker-php-ext-install zip \
  && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copy composer.json for the test application
COPY check-deployed-package/composer.json /app/
COPY check-deployed-package/check.php /app/

# Install dependencies including the published logdash package from Packagist
RUN composer install --no-dev --optimize-autoloader

# Run the application
CMD ["php", "check.php"]
