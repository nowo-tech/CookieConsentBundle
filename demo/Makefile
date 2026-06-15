DEMOS := symfony8 symfony8-tailwind

.PHONY: help up down test-all release-check release-verify clean

help:
	@echo "Cookie Consent Bundle - Demo Commands"
	@echo ""
	@echo "  make up-symfony8           Start Symfony 8 demo (Bootstrap)"
	@echo "  make up-symfony8-tailwind  Start Symfony 8 demo (Tailwind)"
	@echo "  make down-symfony8         Stop Symfony 8 demo"
	@echo "  make down-symfony8-tailwind Stop Tailwind demo"
	@echo "  make test-symfony8         Run Symfony 8 demo tests"
	@echo "  make test-symfony8-tailwind Run Tailwind demo tests"
	@echo "  make release-check         test-all + release-verify"
	@echo ""

up-symfony8:
	@$(MAKE) -C symfony8 up

up-symfony8-tailwind:
	@$(MAKE) -C symfony8-tailwind up

down-symfony8:
	@$(MAKE) -C symfony8 down

down-symfony8-tailwind:
	@$(MAKE) -C symfony8-tailwind down

test-symfony8:
	@$(MAKE) -C symfony8 test

test-symfony8-tailwind:
	@$(MAKE) -C symfony8-tailwind test

# REQ-DEMO-007: update-bundle runs via each demo's test target before composer test
test-all: test-symfony8 test-symfony8-tailwind

release-verify:
	@$(MAKE) up-symfony8
	@sleep 10
	@PORT=$$(grep '^PORT=' symfony8/.env 2>/dev/null | cut -d= -f2 | tr -d '\r'); \
	[ -z "$$PORT" ] && PORT=$$(grep '^PORT=' symfony8/.env.example | cut -d= -f2 | tr -d '\r'); \
	code=$$(curl -s -o /dev/null -w "%{http_code}" http://127.0.0.1:$$PORT 2>/dev/null || echo "000"); \
	$(MAKE) down-symfony8; \
	if [ "$$code" != "200" ]; then echo "Bootstrap demo healthcheck failed (HTTP $$code)"; exit 1; fi; \
	echo "Symfony 8 Bootstrap demo OK (HTTP $$code)"
	@$(MAKE) up-symfony8-tailwind
	@sleep 10
	@PORT=$$(grep '^PORT=' symfony8-tailwind/.env 2>/dev/null | cut -d= -f2 | tr -d '\r'); \
	[ -z "$$PORT" ] && PORT=$$(grep '^PORT=' symfony8-tailwind/.env.example | cut -d= -f2 | tr -d '\r'); \
	code=$$(curl -s -o /dev/null -w "%{http_code}" http://127.0.0.1:$$PORT 2>/dev/null || echo "000"); \
	$(MAKE) down-symfony8-tailwind; \
	if [ "$$code" != "200" ]; then echo "Tailwind demo healthcheck failed (HTTP $$code)"; exit 1; fi; \
	echo "Symfony 8 Tailwind demo OK (HTTP $$code)"

release-check: test-all release-verify
	@echo "All demo checks passed."

clean:
	rm -rf symfony8/vendor symfony8/var symfony8/.phpunit.cache
	rm -rf symfony8-tailwind/vendor symfony8-tailwind/var symfony8-tailwind/.phpunit.cache

BUNDLE_ROOT := $(abspath $(dir $(lastword $(MAKEFILE_LIST)))/..)
include $(BUNDLE_ROOT)/../.scripts/Makefile.demo-aggregate-update-deps.mk
