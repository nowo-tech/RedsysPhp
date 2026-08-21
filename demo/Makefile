DEMOS := symfony8

.PHONY: help up down test test-coverage release-check verify demo-smoke update-bundle \
	$(foreach demo,$(DEMOS),up-$(demo) down-$(demo) test-$(demo) test-coverage-$(demo) verify-$(demo))

help:
	@echo "Redsys PHP SDK Demo"
	@echo "make up-symfony8 | down-symfony8 | verify-symfony8 | demo-smoke"

up-symfony8:
	@$(MAKE) -C symfony8 up

down-symfony8:
	@$(MAKE) -C symfony8 down

test-symfony8:
	@$(MAKE) -C symfony8 test

test-coverage-symfony8:
	@$(MAKE) -C symfony8 test-coverage

verify-symfony8:
	@$(MAKE) -C symfony8 verify

update-bundle:
	@$(MAKE) -C symfony8 update-bundle

up:
	@[ -n "$(DEMO)" ] || { echo "Usage: make up DEMO=symfony8"; exit 1; }
	@$(MAKE) -C $(DEMO) up

down:
	@[ -n "$(DEMO)" ] || { echo "Usage: make down DEMO=symfony8"; exit 1; }
	@$(MAKE) -C $(DEMO) down

test:
	@[ -n "$(DEMO)" ] || { echo "Usage: make test DEMO=symfony8"; exit 1; }
	@$(MAKE) -C $(DEMO) test

test-coverage:
	@[ -n "$(DEMO)" ] || { echo "Usage: make test-coverage DEMO=symfony8"; exit 1; }
	@$(MAKE) -C $(DEMO) test-coverage

verify:
	@[ -n "$(DEMO)" ] || { echo "Usage: make verify DEMO=symfony8"; exit 1; }
	@$(MAKE) verify-$(DEMO)

# REQ-TEST-011: boot primary demo + one HTTP 200
demo-smoke:
	@PORT=$$(grep "^PORT=" symfony8/.env 2>/dev/null | cut -d= -f2 | tr -d '\r'); \
	[ -z "$$PORT" ] && PORT=$$(grep "^PORT=" symfony8/.env.example 2>/dev/null | cut -d= -f2 | tr -d '\r'); \
	[ -z "$$PORT" ] && PORT=8020; \
	echo "Demo smoke: symfony8 (http://127.0.0.1:$$PORT/)"; \
	$(MAKE) -C symfony8 up || exit 1; \
	sleep 12; \
	code=$$(curl -s -o /dev/null -w "%{http_code}" "http://127.0.0.1:$$PORT/" || true); \
	[ -z "$$code" ] && code=000; \
	$(MAKE) -C symfony8 down 2>/dev/null || true; \
	if [ "$$code" != "200" ]; then echo "symfony8 failed with HTTP $$code"; exit 1; fi; \
	echo "symfony8 OK (HTTP $$code)"

release-check:
	@$(MAKE) -C symfony8 release-check

BUNDLE_ROOT := $(abspath $(dir $(lastword $(MAKEFILE_LIST)))/..)
-include $(BUNDLE_ROOT)/../.scripts/Makefile.demo-aggregate-update-deps.mk
