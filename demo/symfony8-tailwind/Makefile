# Prefer Compose V2 plugin (GitHub Actions / modern Docker Desktop); fall back to docker-compose V1 (REQ-MAKE-010).
# Always invoke via $(call dc,...) so Make routes through the shell — direct execve of the WSL
# Docker Desktop CLI symlink fails with "Permission denied" (EACCES).
COMPOSE_BIN := $(shell docker compose version >/dev/null 2>&1 && echo "docker compose" || echo "docker-compose")
dc = $(SHELL) -c '$(COMPOSE_BIN) $(1)'

.PHONY: help up down build install shell logs test test-coverage cache-clear update-bundle ensure-up restart setup-db

BUNDLE_ROOT := $(abspath $(dir $(lastword $(MAKEFILE_LIST)))/../..)
export DOCKER_CONFIG := $(BUNDLE_ROOT)/.docker
SERVICE_PHP := php

help:
	@echo "Symfony 8 Tailwind Demo - Cookie Consent Bundle"
	@echo ""
	@echo "  make up             Start containers"
	@echo "  make down           Stop containers"
	@echo "  make restart        Restart containers"
	@echo "  make build          Build images (no cache)"
	@echo "  make install        Install dependencies"
	@echo "  make setup-db       Run Doctrine migrations"
	@echo "  make shell          Open shell in PHP container"
	@echo "  make test           Run tests"
	@echo "  make update-bundle  Refresh bundle autoload and cache"

# REQ-DEMO-005: canonical up — Waiting…, Installing…, Demo started at: from PORT
up:
	@if [ ! -f .env ]; then cp .env.example .env 2>/dev/null || true; fi
	@$(call dc,up -d)
	@echo "Waiting for container to be ready..."
	@sleep 5
	@echo "Installing dependencies..."
	@$(call dc,exec -T -e COMPOSER_MEMORY_LIMIT=-1 php composer install --no-interaction)
	@$(MAKE) setup-db
	@PORT=$$(grep "^PORT=" .env 2>/dev/null | cut -d= -f2 | tr -d '\r'); \
	[ -z "$$PORT" ] && PORT=$$(grep "^PORT=" .env.example 2>/dev/null | cut -d= -f2 | tr -d '\r'); \
	echo "Demo started at: http://localhost:$$PORT"

down:
	@$(call dc,down)

restart: ensure-up
	@$(call dc,restart)

build:
	@$(call dc,build --no-cache)

ensure-up:
	@if ! $(call dc,exec -T php true) >/dev/null 2>&1; then \
		echo "Starting container..."; \
		$(call dc,up -d); \
		sleep 3; \
	fi

install: ensure-up
	@$(call dc,exec -T -e COMPOSER_MEMORY_LIMIT=-1 php composer install --no-interaction)

setup-db: ensure-up
	@$(call dc,exec -T php php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration) || true
	@$(call dc,exec -T php php bin/console demo:seed-cookie-consent-config --if-empty --apply-playground)
	@$(call dc,exec -T php php bin/console demo:seed-cookie-definitions)

shell: ensure-up
	@$(call dc,exec php sh)

logs:
	@$(call dc,logs -f)

# REQ-DEMO-007: sync mounted bundle before tests
test: ensure-up update-bundle setup-db
	@$(call dc,exec -e APP_ENV=test php composer test)

test-coverage: ensure-up setup-db
	@$(call dc,exec -e APP_ENV=test php composer test-coverage)

cache-clear: ensure-up
	@$(call dc,exec php php bin/console cache:clear)

update-bundle: ensure-up
	@$(call dc,exec php composer dump-autoload --optimize)
	@$(call dc,exec php php bin/console cache:clear --env=dev --no-warmup) || true
	@echo "Bundle updated. Reload the page in the browser."

# REQ-MAKE-008: update-deps (REQ-MAKE-008)
# Optional: monorepo helper absent on standalone GitHub Actions checkout (REQ-MAKE-009).
-include $(BUNDLE_ROOT)/../.scripts/Makefile.demo-update-deps.mk
