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

# Copy the source files (they should be available in the build context)
COPY src /app/logdash-php-sdk/src
COPY composer.json /app/logdash-php-sdk/composer.json

# Copy test files
COPY check-deployed-package/check.php /app/

# Create a simple composer.json that uses the local version and its dependencies
RUN echo '{"require": {"php": ">=8.1", "guzzlehttp/guzzle": "^7.0"}, "autoload": {"psr-4": {"Logdash\\\\": "logdash-php-sdk/src/"}}}' > composer.json

# Install dependencies and generate autoloader
RUN composer install --no-dev --optimize-autoloader

# Run the application
CMD ["php", "check.php"]
