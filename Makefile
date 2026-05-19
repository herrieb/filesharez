.DEFAULT_GOAL := help

help: ## Show this help
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-30s\033[0m %s\n", $$1, $$2}'

build: ## Build Docker containers
	docker compose build

up: ## Start all containers
	docker compose up -d

down: ## Stop all containers
	docker compose down

restart: ## Restart all containers
	docker compose restart

logs: ## View container logs
	docker compose logs -f app

shell: ## Open a shell in the app container
	docker compose exec app bash

composer: ## Install composer dependencies
	docker compose exec app composer install --optimize-autoloader

migrate: ## Run database migrations
	docker compose exec app php bin/console doctrine:migrations:migrate --no-interaction

schema-create: ## Create database schema
	docker compose exec app php bin/console doctrine:schema:create

init-admin: ## Create admin user (usage: make init-admin EMAIL=admin@filesharez.local PASSWORD=changeme)
	docker compose exec app php bin/console app:init-admin $(EMAIL) $(PASSWORD)

cleanup: ## Run cleanup command
	docker compose exec app php bin/console app:cleanup-expired-transfers

cache: ## Clear cache
	docker compose exec app php bin/console cache:clear

permissions: ## Fix file permissions
	docker compose exec app chmod -R 775 var/