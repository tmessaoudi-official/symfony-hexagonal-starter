.PHONY: setup dev down shell logs test test-unit test-integration test-functional test-e2e \
        lint cs-fix cs-check analyze rector-dry rector mutation coverage \
        db-migrate db-reset db-test worker

# ─── Variables ────────────────────────────────────────────────────────────────
DC      := docker compose
APP     := $(DC) exec app
CONSOLE := $(APP) php bin/console

# Test runs override the dev container's environment. Compose exports APP_ENV=dev and the dev
# DATABASE_URL as real environment variables, and KernelTestCase reads $_ENV before the APP_ENV
# that phpunit.xml.dist forces into $_SERVER: without these, the suites boot the dev kernel
# against the dev database. Values match docker-compose.test.yml.
TEST_ENV := -e APP_ENV=test \
            -e 'DATABASE_URL=postgresql://app:app@postgres:5432/app_test?serverVersion=19&charset=utf8' \
            -e MESSENGER_TRANSPORT_DSN=in-memory:// -e MAILER_DSN=null://null
APP_TEST := $(DC) exec $(TEST_ENV) app

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

## db-test: Create and migrate the app_test database the DB suites run against
db-test:
	$(APP_TEST) php bin/console doctrine:database:create --if-not-exists
	$(APP_TEST) php bin/console doctrine:migrations:migrate --no-interaction

## test: Run all test suites (unit + integration + functional)
test: db-test
	$(APP_TEST) vendor/bin/pest

## test-unit: Run unit tests only (no DB, blazing fast)
test-unit:
	$(APP_TEST) vendor/bin/pest --testsuite=Unit

## test-integration: Run integration tests (requires DB)
test-integration: db-test
	$(APP_TEST) vendor/bin/pest --testsuite=Integration

## test-functional: Run HTTP-level functional tests (requires DB)
test-functional: db-test
	$(APP_TEST) vendor/bin/pest --testsuite=Functional

## test-e2e: Run Behat E2E API scenarios
test-e2e: db-test
	$(APP_TEST) vendor/bin/behat

## coverage: Generate HTML coverage report (output: build/coverage/)
coverage: db-test
	$(APP_TEST) php -d pcov.enabled=1 vendor/bin/pest --coverage --coverage-html=build/coverage

## mutation: Mutation testing - Infection on src/Domain, Pest on src/Application (docs/testing.md)
mutation:
	$(APP_TEST) composer mutation

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
