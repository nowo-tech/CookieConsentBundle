COMPOSE = docker compose
SERVICE_PHP = php

# WSL: avoid broken docker-credential-desktop.exe when pulling public base images.
BUNDLE_ROOT := $(abspath $(dir $(lastword $(MAKEFILE_LIST))))
export DOCKER_CONFIG := $(BUNDLE_ROOT)/.docker

.PHONY: help ensure-up up down build shell install assets assets-test test-ts test test-coverage test-with-db \
	test-coverage-with-db cs-check cs-fix rector rector-dry phpstan validate-phpdoc qa release-check \
	release-check-demos composer-sync clean update validate validate-translations \
	setup-hooks check-no-cursor-coauthor strip-cursor-coauthor-from-history

help:
	@echo "Cookie Consent Bundle - Development Commands"
	@echo ""
	@echo "Usage: make <target>"
	@echo ""
	@echo "Container: up down build shell"
	@echo "Dependencies: install assets"
	@echo "Tests: test test-coverage test-with-db test-coverage-with-db test-ts"
	@echo "Quality: cs-check cs-fix rector rector-dry phpstan qa validate-translations"
	@echo "Release: release-check composer-sync"
	@echo "Cleanup: clean"
	@echo "Composer: update update-deps validate"

ensure-up:
	@mkdir -p "$(BUNDLE_ROOT)/.docker"
	@test -f "$(BUNDLE_ROOT)/.docker/config.json" || printf '%s\n' '{}' > "$(BUNDLE_ROOT)/.docker/config.json"
	@$(COMPOSE) ps -q $(SERVICE_PHP) >/dev/null 2>&1 || true
	@$(COMPOSE) up -d --build
	@sleep 2
	@$(COMPOSE) exec -T $(SERVICE_PHP) sh -lc 'test -d vendor || composer install --no-interaction'
	@$(COMPOSE) exec -T -e CI=true $(SERVICE_PHP) sh -lc 'test -d node_modules || pnpm install'

up:
	@$(MAKE) ensure-up

down:
	@$(COMPOSE) down

build:
	@$(COMPOSE) build --no-cache

shell:
	@$(COMPOSE) exec $(SERVICE_PHP) sh

install: ensure-up
	@$(COMPOSE) exec -T $(SERVICE_PHP) composer install --no-interaction
	@$(COMPOSE) exec -T -e CI=true $(SERVICE_PHP) pnpm install

assets: ensure-up
	@$(COMPOSE) exec -T -e CI=true $(SERVICE_PHP) pnpm install
	@$(COMPOSE) exec -T $(SERVICE_PHP) pnpm run build
	@echo "Assets: src/Resources/public/nowo-consent-modal.js"

test-ts: ensure-up
	@$(COMPOSE) exec -T $(SERVICE_PHP) pnpm run test:coverage | tee coverage-ts.txt
	@./.scripts/ts-coverage-percent.sh coverage-ts.txt

assets-test: test-ts

test: ensure-up
	@$(COMPOSE) exec -T $(SERVICE_PHP) composer test

test-with-db: test

test-coverage: ensure-up
	@$(COMPOSE) exec -T $(SERVICE_PHP) composer test-coverage | tee coverage-php.txt
	@./.scripts/php-coverage-percent.sh coverage-php.txt

test-coverage-with-db: test-coverage

cs-check: ensure-up
	@$(COMPOSE) exec -T $(SERVICE_PHP) composer cs-check

cs-fix: ensure-up
	@$(COMPOSE) exec -T $(SERVICE_PHP) composer cs-fix

rector-dry: ensure-up
	@$(COMPOSE) exec -T $(SERVICE_PHP) composer rector-dry

rector: ensure-up
	@$(COMPOSE) exec -T $(SERVICE_PHP) composer rector

validate-phpdoc: ensure-up
	@$(COMPOSE) exec -T $(SERVICE_PHP) php .scripts/complete-public-phpdoc.php

phpstan: ensure-up
	@$(COMPOSE) exec -T $(SERVICE_PHP) composer phpstan

validate-translations: ensure-up
	@echo "Translation files validated (YAML syntax only)."
	@$(COMPOSE) exec -T $(SERVICE_PHP) php -r "foreach (glob('src/Resources/translations/*.yaml') as $$f) { Symfony\\Component\\Yaml\\Yaml::parseFile($$f); } echo \"OK\\n\";"

qa: cs-check test test-ts

release-check: check-no-cursor-coauthor ensure-up assets composer-sync cs-fix cs-check rector-dry phpstan validate-phpdoc test-coverage test-ts release-check-demos

release-check-demos:
	@if [ -f demo/Makefile ]; then $(MAKE) -C demo release-check 2>/dev/null || true; else true; fi

composer-sync: ensure-up
	@$(COMPOSE) exec -T $(SERVICE_PHP) composer validate --strict
	@$(COMPOSE) exec -T $(SERVICE_PHP) composer update --lock --no-install

clean:
	rm -rf vendor node_modules .pnpm-store coverage coverage-ts .phpunit.cache coverage.xml .php-cs-fixer.cache coverage-php.txt coverage-ts.txt

update: ensure-up
	@$(COMPOSE) exec -T $(SERVICE_PHP) composer update

validate: ensure-up
	@$(COMPOSE) exec -T $(SERVICE_PHP) composer validate --strict


setup-hooks:
	@chmod +x .githooks/pre-commit 2>/dev/null || true
	@chmod +x .githooks/commit-msg 2>/dev/null || true
	@git config core.hooksPath .githooks
	@echo "✅ Git hooks installed (.githooks — includes commit-msg for REQ-GIT-001)."

# REQ-MAKE-008: update-deps (REQ-MAKE-008)
include $(BUNDLE_ROOT)/../.scripts/Makefile.update-deps.mk
check-no-cursor-coauthor:
	@chmod +x .scripts/check-no-cursor-coauthor.sh
	@./.scripts/check-no-cursor-coauthor.sh HEAD

strip-cursor-coauthor-from-history:
	@chmod +x .scripts/strip-cursor-coauthor-from-history.sh
	@./.scripts/strip-cursor-coauthor-from-history.sh master
