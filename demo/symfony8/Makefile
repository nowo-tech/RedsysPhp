.PHONY: help up down build install shell logs test test-coverage cache-clear link-bundle update-bundle ensure-up restart release-check verify test-e2e demo-screenshots

help:
	@echo "Symfony 8 Demo - Redsys PHP SDK"

up:
	@if [ ! -f .env ]; then cp .env.example .env 2>/dev/null || true; fi
	# Boot classic first: worker mode crashes if vendor/ is missing (no autoload_runtime.php yet).
	FRANKENPHP_MODE=classic $(DOCKER_COMPOSE) up -d --build
	@echo "Waiting for container to be ready..."
	@sleep 5
	@echo "Installing dependencies..."
	@$(DOCKER_COMPOSE) exec -T -e COMPOSER_MEMORY_LIMIT=-1 php composer install --no-interaction
	@$(MAKE) link-bundle
	@# Recreate with FRANKENPHP_MODE from .env (default worker)
	$(DOCKER_COMPOSE) up -d
	@sleep 2
	@PORT=$$(grep "^PORT=" .env 2>/dev/null | cut -d= -f2 | tr -d '\r'); \
	[ -z "$$PORT" ] && PORT=$$(grep "^PORT=" .env.example 2>/dev/null | cut -d= -f2 | tr -d '\r'); \
	echo "Demo started at: http://localhost:$$PORT"

down:
	$(DOCKER_COMPOSE) down

restart: ensure-up
	$(DOCKER_COMPOSE) restart

build:
	$(DOCKER_COMPOSE) build --no-cache

ensure-up:
	@if ! $(DOCKER_COMPOSE) exec -T php true 2>/dev/null; then $(DOCKER_COMPOSE) up -d; sleep 3; fi

install: ensure-up
	$(DOCKER_COMPOSE) exec -T -e COMPOSER_MEMORY_LIMIT=-1 php composer install --no-interaction

shell: ensure-up
	$(DOCKER_COMPOSE) exec php sh

logs:
	$(DOCKER_COMPOSE) logs -f

test: ensure-up
	$(DOCKER_COMPOSE) exec -e APP_ENV=test php composer test

test-coverage: ensure-up
	$(DOCKER_COMPOSE) exec php composer test-coverage

cache-clear: ensure-up
	$(DOCKER_COMPOSE) exec php php bin/console cache:clear

link-bundle: ensure-up
	$(DOCKER_COMPOSE) exec php sh -c "rm -rf vendor/nowo-tech/redsys-php && ln -sfn /var/redsys-php vendor/nowo-tech/redsys-php && php bin/console cache:clear"

update-bundle: ensure-up
	$(DOCKER_COMPOSE) exec php composer update nowo-tech/redsys-php --no-interaction
	$(DOCKER_COMPOSE) exec php sh -c "php bin/console cache:clear --env=dev"

verify:
	@if [ ! -f .env ]; then cp .env.example .env 2>/dev/null || true; fi
	@port=$$(grep '^PORT=' .env 2>/dev/null | cut -d= -f2 | tr -d '\r'); \
	[ -z "$$port" ] && port=$$(grep '^PORT=' .env.example 2>/dev/null | cut -d= -f2 | tr -d '\r'); \
	[ -z "$$port" ] && port=8020; \
	$(DOCKER_COMPOSE) up -d 2>/dev/null; sleep 8; \
	code=$$(curl -s -o /dev/null -w "%{http_code}" http://127.0.0.1:$$port/ 2>/dev/null || echo "000"); \
	if [ "$$code" = "200" ]; then echo "OK (HTTP $$code)"; else echo "Failed (HTTP $$code)"; exit 1; fi

release-check: ensure-up update-bundle test test-coverage

# REQ-DEMO-013: Playwright e2e and README screenshots (official Playwright image, nothing to install on the host).
# The image tag always follows the exact @playwright/test version pinned in e2e/package.json (browsers ship with the image).
PLAYWRIGHT_VERSION := $(shell sed -n 's/.*"@playwright\/test": "\([0-9.]*\)".*/\1/p' e2e/package.json)
PLAYWRIGHT_IMAGE ?= mcr.microsoft.com/playwright:v$(PLAYWRIGHT_VERSION)-jammy

test-e2e: ensure-up
	@mkdir -p e2e/node_modules
	@PORT=$$(grep -E '^PORT=' .env 2>/dev/null | cut -d= -f2 | tr -d '\r'); PORT=$${PORT:-8020}; \
	docker run --rm --network host \
		-v "$(CURDIR)/e2e:/e2e" -w /e2e \
		-e DEMO_BASE_URL=http://127.0.0.1:$$PORT \
		$(PLAYWRIGHT_IMAGE) \
		bash -lc 'npm ci --no-fund --no-audit && npx playwright test --project=chromium'

demo-screenshots: ensure-up
	@mkdir -p ../../docs/images/demo e2e/node_modules
	@PORT=$$(grep -E '^PORT=' .env 2>/dev/null | cut -d= -f2 | tr -d '\r'); PORT=$${PORT:-8020}; \
	docker run --rm --network host \
		-v "$(CURDIR)/e2e:/e2e" \
		-v "$(CURDIR)/../../docs/images/demo:/screenshots" \
		-w /e2e \
		-e DEMO_BASE_URL=http://127.0.0.1:$$PORT \
		-e SCREENSHOT_DIR=/screenshots \
		$(PLAYWRIGHT_IMAGE) \
		bash -lc 'npm ci --no-fund --no-audit && npx playwright test --project=screenshots && node scripts/to-webp.mjs /screenshots'
	@echo "Screenshots written under docs/images/demo/ (WebP)"

# Prefer Compose V2; absolute docker path avoids shadowing by local docker/ when PATH has "." (REQ-MAKE-010).
DOCKER_BIN := $(shell PATH="/usr/local/bin:/usr/bin:/bin:$$PATH" command -v docker 2>/dev/null)
ifeq ($(DOCKER_BIN),)
DOCKER_COMPOSE ?= docker-compose
else
DOCKER_COMPOSE ?= $(shell $(DOCKER_BIN) compose version >/dev/null 2>&1 && echo "$(DOCKER_BIN) compose" || echo "docker-compose")
endif
SERVICE_PHP := php
# Optional: monorepo helper absent on standalone GitHub Actions checkout (REQ-MAKE-009).
-include $(abspath $(dir $(lastword $(MAKEFILE_LIST)))/../../../.scripts/Makefile.demo-update-deps.mk)
