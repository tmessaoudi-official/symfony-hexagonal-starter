.PHONY: setup dev down shell logs test test-unit test-integration test-e2e \
        lint cs-fix cs-check analyze rector-dry rector mutation coverage \
        db-migrate db-fixtures db-reset worker

# ─── Variables ────────────────────────────────────────────────────────────────
DC      := docker compose
APP     := $(DC) exec app
CONSOLE := $(APP) php bin/console

# ─── Setup & Start ────────────────────────────────────────────────────────────

## setup: First-time setup — build images, install deps, run migrations
setup:
	$(DC) build
	$(DC) up -d
	$(APP) composer install
	$(MAKE) db-migrate
	@echo ""
	@echo "Setup complete. API is available at http://localhost:8080/api"
	@echo "API docs at http://localhost:8080/api/docs"
	@echo "Mailpit UI at http://localhost:8025"
	@echo "Adminer at http://localhost:8081"

## dev: Start all services in the background
dev:
	$(DC) up -d

## down: Stop and remove all containers
down:
	$(DC) down

## shell: Open a bash shell in the app container
shell:
	$(APP) bash

## logs: Follow app container logs
logs:
	$(DC) logs -f app

# ─── Testing ──────────────────────────────────────────────────────────────────

## test: Run all test suites (unit + integration)
test:
	$(APP) vendor/bin/pest

## test-unit: Run unit tests only (no DB, blazing fast)
test-unit:
	$(APP) vendor/bin/pest --testsuite=Unit

## test-integration: Run integration tests (requires DB)
test-integration:
	$(APP) vendor/bin/pest --testsuite=Integration

## test-e2e: Run Behat E2E API scenarios
test-e2e:
	$(APP) vendor/bin/behat

## coverage: Generate HTML coverage report (output: build/coverage/)
coverage:
	$(APP) php -d pcov.enabled=1 vendor/bin/pest --coverage --coverage-html=build/coverage

## mutation: Run Infection mutation testing (MSI target: 80%)
mutation:
	$(APP) vendor/bin/infection --threads=4

# ─── Code Quality ─────────────────────────────────────────────────────────────

## lint: Run PHPStan static analysis (level 8)
lint: analyze

## analyze: Alias for PHPStan
analyze:
	$(APP) vendor/bin/phpstan analyse --memory-limit=512M

## cs-fix: Fix code style with PHP-CS-Fixer
cs-fix:
	$(APP) vendor/bin/php-cs-fixer fix --allow-risky=yes

## cs-check: Dry-run PHP-CS-Fixer (reports issues without fixing)
cs-check:
	$(APP) vendor/bin/php-cs-fixer fix --allow-risky=yes --dry-run --diff

## rector-dry: Preview Rector refactoring suggestions (no writes)
rector-dry:
	$(APP) vendor/bin/rector process --dry-run

## rector: Apply Rector refactoring
rector:
	$(APP) vendor/bin/rector process

# ─── Database ─────────────────────────────────────────────────────────────────

## db-migrate: Run pending Doctrine migrations
db-migrate:
	$(CONSOLE) doctrine:migrations:migrate --no-interaction

## db-fixtures: Load test fixtures via Foundry
db-fixtures:
	$(CONSOLE) doctrine:fixtures:load --no-interaction

## db-reset: Drop schema, re-create, run migrations (WARNING: destroys data)
db-reset:
	$(CONSOLE) doctrine:database:drop --force --if-exists
	$(CONSOLE) doctrine:database:create
	$(MAKE) db-migrate

# ─── Worker ───────────────────────────────────────────────────────────────────

## worker: Start the Messenger worker in the foreground
worker:
	$(CONSOLE) messenger:consume async --time-limit=3600 -vv

# ─── Help ─────────────────────────────────────────────────────────────────────

help:
	@grep -E '^## ' Makefile | sed 's/## //' | column -t -s ':'
