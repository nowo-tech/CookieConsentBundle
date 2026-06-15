.PHONY: help up down build install shell logs test test-coverage cache-clear update-bundle ensure-up restart setup-db

BUNDLE_ROOT := $(abspath $(dir $(lastword $(MAKEFILE_LIST)))/../..)
export DOCKER_CONFIG := $(BUNDLE_ROOT)/.docker

help:
	@echo "Symfony 8 Demo - Cookie Consent Bundle"
	@echo ""
	@echo "  make up             Start containers"
	@echo "  make down           Stop containers"
	@echo "  make build          Build images (no cache)"
	@echo "  make install        Install dependencies"
	@echo "  make setup-db       Run Doctrine migrations"
	@echo "  make shell          Open shell in PHP container"
	@echo "  make test           Run tests"
	@echo "  make update-bundle  Refresh bundle autoload and cache"

# REQ-DEMO-005: canonical up — Waiting…, Installing…, Demo started at: from PORT
up:
	@if [ ! -f .env ]; then cp .env.example .env 2>/dev/null || true; fi
	docker-compose up -d
	@echo "Waiting for container to be ready..."
	@sleep 5
	@echo "Installing dependencies..."
	@docker-compose exec -T -e COMPOSER_MEMORY_LIMIT=-1 php composer install --no-interaction
	@$(MAKE) setup-db
	@PORT=$$(grep "^PORT=" .env 2>/dev/null | cut -d= -f2 | tr -d '\r'); \
	[ -z "$$PORT" ] && PORT=$$(grep "^PORT=" .env.example 2>/dev/null | cut -d= -f2 | tr -d '\r'); \
	echo "Demo started at: http://localhost:$$PORT"

down:
	docker-compose down

build:
	docker-compose build --no-cache

ensure-up:
	@if ! docker-compose exec -T php true 2>/dev/null; then \
		echo "Starting container..."; \
		docker-compose up -d; \
		sleep 3; \
	fi

install: ensure-up
	docker-compose exec -T -e COMPOSER_MEMORY_LIMIT=-1 php composer install --no-interaction

setup-db: ensure-up
	docker-compose exec -T php php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration || true
	docker-compose exec -T php php bin/console demo:seed-cookie-consent-config --if-empty --apply-playground
	docker-compose exec -T php php bin/console demo:seed-cookie-definitions

shell: ensure-up
	docker-compose exec php sh

logs:
	docker-compose logs -f

# REQ-DEMO-007: sync mounted bundle before tests
test: ensure-up update-bundle setup-db
	docker-compose exec -e APP_ENV=test php composer test

test-coverage: ensure-up setup-db
	docker-compose exec -e APP_ENV=test php composer test-coverage

cache-clear: ensure-up
	docker-compose exec php php bin/console cache:clear

update-bundle: ensure-up
	docker-compose exec php composer dump-autoload --optimize
	docker-compose exec php php bin/console cache:clear --env=dev --no-warmup || true
	@echo "Bundle updated. Reload the page in the browser."

# REQ-MAKE-008: update-deps (REQ-MAKE-008)
include $(BUNDLE_ROOT)/../.scripts/Makefile.demo-update-deps.mk
