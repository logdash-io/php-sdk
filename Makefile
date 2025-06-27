.PHONY: help install test examples docker-build docker-test docker-examples clean

help: ## Show this help message
	@echo 'Usage: make [target]'
	@echo ''
	@echo 'Targets:'
	@awk 'BEGIN {FS = ":.*?## "} /^[a-zA-Z_-]+:.*?## / {printf "  %-15s %s\n", $$1, $$2}' $(MAKEFILE_LIST)

install: ## Install dependencies
	composer install

test: ## Run tests
	php test.php
	@if command -v vendor/bin/phpunit >/dev/null 2>&1; then \
		vendor/bin/phpunit; \
	else \
		echo "PHPUnit not available, run 'make install' first"; \
	fi

examples: ## Run examples
	php examples.php

phpstan: ## Run PHPStan static analysis
	@if command -v vendor/bin/phpstan >/dev/null 2>&1; then \
		vendor/bin/phpstan analyse; \
	else \
		echo "PHPStan not available, run 'make install' first"; \
	fi

cs: ## Check code style
	@if command -v vendor/bin/phpcs >/dev/null 2>&1; then \
		vendor/bin/phpcs; \
	else \
		echo "PHP CodeSniffer not available, run 'make install' first"; \
	fi

cbf: ## Fix code style
	@if command -v vendor/bin/phpcbf >/dev/null 2>&1; then \
		vendor/bin/phpcbf; \
	else \
		echo "PHP Code Beautifier not available, run 'make install' first"; \
	fi

docker-build: ## Build Docker image
	docker-compose build

docker-test: ## Run tests in Docker
	docker-compose run --rm php-test

docker-examples: ## Run examples in Docker
	docker-compose run --rm php-sdk

docker-dev: ## Start development environment
	docker-compose run --rm php-dev

clean: ## Clean up generated files
	rm -rf vendor/
	rm -f composer.lock
	rm -rf .phpunit.cache
	docker-compose down --rmi all --volumes --remove-orphans 2>/dev/null || true
