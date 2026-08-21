# Makefile for Redsys PHP SDK (Nowo fork)
# REQ-MAKE-001 / REQ-MAKE-002 / REQ-MAKE-006 / REQ-MAKE-008 / REQ-MAKE-010

.PHONY: help ensure-up up down down-dev build shell install assets test test-coverage cs-check cs-fix rector rector-dry phpstan qa release-check composer-sync clean update validate setup-hooks check-no-cursor-coauthor strip-cursor-coauthor-from-history

COMPOSER_BIN := /usr/bin/composer
COMPOSE := $(shell docker compose version >/dev/null 2>&1 && echo "docker compose" || echo "docker-compose")

help:
	@echo "Redsys PHP SDK — Development Commands"
	@echo ""
	@echo "Usage: make <target>"
	@echo ""
	@echo "Targets:"
	@echo "  up            Start Docker container"
	@echo "  down          Stop Docker container"
	@echo "  down-dev      Stop root compose and remove orphans"
	@echo "  build         Build Docker image"
	@echo "  shell         Open shell in container"
	@echo "  install       Install Composer dependencies"
	@echo "  test          Run PHPUnit tests"
	@echo "  test-coverage Run tests with code coverage"
	@echo "  cs-check      Check code style"
	@echo "  cs-fix        Fix code style"
	@echo "  rector        Apply Rector refactoring"
	@echo "  rector-dry    Run Rector in dry-run mode"
	@echo "  phpstan       Run PHPStan static analysis"
	@echo "  qa            Run QA checks (cs-check + phpstan + test)"
	@echo "  release-check Run full pre-release validation chain"
	@echo "  setup-hooks   Install git hooks (REQ-GIT-001)"

ensure-up:
	@if ! $(COMPOSE) exec -T php true 2>/dev/null; then \
		echo "Starting container..."; \
		$(COMPOSE) up -d --build; \
		sleep 3; \
		$(COMPOSE) exec -T php $(COMPOSER_BIN) install --no-interaction; \
	fi

build:
	$(COMPOSE) build

up:
	@echo "Building Docker image..."
	$(COMPOSE) build
	@echo "Starting container..."
	$(COMPOSE) up -d
	@echo "Waiting for container to be ready..."
	@sleep 2
	@echo "Installing dependencies..."
	$(COMPOSE) exec -T php $(COMPOSER_BIN) install --no-interaction
	@echo "Container ready"

down:
	$(COMPOSE) down

down-dev:
	$(COMPOSE) down --remove-orphans

shell: ensure-up
	$(COMPOSE) exec php sh

install: ensure-up
	$(COMPOSE) exec -T php $(COMPOSER_BIN) install --no-interaction

assets:
	@echo "No frontend assets in this library."

test: ensure-up
	$(COMPOSE) exec php $(COMPOSER_BIN) test

test-coverage: ensure-up
	$(COMPOSE) exec php $(COMPOSER_BIN) test-coverage | tee coverage-php.txt
	sh ./.scripts/php-coverage-percent.sh coverage-php.txt

cs-check: ensure-up
	$(COMPOSE) exec -T php $(COMPOSER_BIN) cs-check

cs-fix: ensure-up
	$(COMPOSE) exec -T php $(COMPOSER_BIN) cs-fix

rector: ensure-up
	$(COMPOSE) exec -T php $(COMPOSER_BIN) rector

rector-dry: ensure-up
	$(COMPOSE) exec -T php $(COMPOSER_BIN) rector-dry

phpstan: ensure-up
	$(COMPOSE) exec -T php $(COMPOSER_BIN) phpstan

qa: ensure-up
	$(COMPOSE) exec -T php $(COMPOSER_BIN) qa

composer-sync: ensure-up
	$(COMPOSE) exec -T php $(COMPOSER_BIN) validate --strict
	$(COMPOSE) exec -T php $(COMPOSER_BIN) update --lock --no-interaction --no-install

release-check: check-no-cursor-coauthor
	@$(MAKE) ensure-up
	@$(MAKE) composer-sync
	@$(MAKE) cs-fix
	@$(MAKE) cs-check
	@$(MAKE) rector-dry
	@$(MAKE) phpstan
	@$(MAKE) test-coverage

update: ensure-up
	$(COMPOSE) exec -T php $(COMPOSER_BIN) update --no-interaction

validate: ensure-up
	$(COMPOSE) exec -T php $(COMPOSER_BIN) validate --strict

clean:
	rm -rf vendor
	rm -rf .phpunit.cache
	rm -rf coverage
	rm -f coverage.xml
	rm -f coverage-php.txt
	rm -f .php-cs-fixer.cache

check-no-cursor-coauthor:
	@chmod +x .scripts/check-no-cursor-coauthor.sh
	@./.scripts/check-no-cursor-coauthor.sh HEAD

setup-hooks:
	@chmod +x .githooks/pre-commit 2>/dev/null || true
	@chmod +x .githooks/commit-msg 2>/dev/null || true
	@git config core.hooksPath .githooks
	@echo "Git hooks installed (.githooks — includes commit-msg for REQ-GIT-001)."

# REQ-MAKE-008: update-deps (REQ-MAKE-008)
BUNDLE_ROOT := $(abspath $(dir $(lastword $(MAKEFILE_LIST))))
-include $(BUNDLE_ROOT)/../.scripts/Makefile.update-deps.mk

strip-cursor-coauthor-from-history:
	@chmod +x .scripts/strip-cursor-coauthor-from-history.sh
	@./.scripts/strip-cursor-coauthor-from-history.sh master
