#!/usr/bin/make -f

DC=docker compose
APP=motor1000_app

.PHONY: help up down build shell artisan composer migrate seed test horizon logs

help: ## Show this help
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-20s\033[0m %s\n", $$1, $$2}'

up: ## Start all containers
	$(DC) up -d

down: ## Stop all containers
	$(DC) down

build: ## Build containers from scratch
	$(DC) build --no-cache

shell: ## Open bash shell in app container
	$(DC) exec app bash

artisan: ## Run artisan command: make artisan CMD="..."
	$(DC) exec app php artisan $(CMD)

composer: ## Run composer command: make composer CMD="require package"
	$(DC) exec app composer $(CMD)

migrate: ## Run migrations
	$(DC) exec app php artisan migrate

migrate-fresh: ## Fresh migration + seed
	$(DC) exec app php artisan migrate:fresh --seed

seed: ## Run seeders
	$(DC) exec app php artisan db:seed

test: ## Run tests
	$(DC) exec app php artisan test

horizon: ## Open Horizon dashboard logs
	$(DC) logs -f horizon

logs: ## Tail app logs
	$(DC) logs -f app

install: ## Full first-time setup
	cp -n .env.example .env || true
	$(DC) build
	$(DC) up -d
	$(DC) exec app composer install --no-interaction
	$(DC) exec app npm install
	$(DC) exec app npm run build
	$(DC) exec app php artisan key:generate
	$(DC) exec app php artisan migrate --force
	$(DC) exec app php artisan db:seed
	$(DC) exec app php artisan storage:link
	$(DC) exec app php artisan filament:assets
	@echo "\n✅  Motor1000 is ready at http://localhost:8080"
	@echo "📧  Mailpit at http://localhost:8025"
	@echo "🔐  Admin: admin@motor1000.local / password"

pint: ## Run Laravel Pint code formatter
	$(DC) exec app ./vendor/bin/pint

fresh: ## Nuke and rebuild everything
	$(DC) down -v
	make install
