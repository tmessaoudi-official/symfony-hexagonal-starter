# /run-tests

Run the full test suite and report results.

## What This Command Does

### Step 1: Unit Tests (no DB required)
```bash
vendor/bin/pest --testsuite=Unit --colors=always 2>&1
```
Report: pass/fail count, failing test names and messages.

### Step 2: Integration Tests (requires DB)
```bash
# Ensure migrations are applied first
php bin/console doctrine:migrations:migrate --no-interaction --env=test

vendor/bin/pest --testsuite=Integration --colors=always 2>&1
```
Report: pass/fail count. If migrations fail: report the error and stop.

### Step 3: E2E Tests (Behat)
```bash
vendor/bin/behat --colors 2>&1
```
Report: scenario pass/fail counts, failing scenario names and steps.

### Step 4: Coverage Summary (optional — only if pcov extension is available)
```bash
php -r "echo extension_loaded('pcov') ? 'pcov available' : 'pcov not available';"
# If available:
vendor/bin/pest --testsuite=Unit --coverage-text 2>&1
```

---

## Output Format

```
=== Test Results ===

Unit Tests:     ✓ 12 passed (0.8s)
Integration:    ✓ 5 passed (3.2s)
E2E (Behat):    ✓ 7 scenarios, 35 steps (4.1s)

Total: 24 tests, 0 failures
```

**On failure:**

```
=== Test Results ===

Unit Tests:     ✗ 11 passed, 1 FAILED

FAILED: UserTest > deactivates and reactivates a user
  Expected: UserStatus::Inactive
  Actual:   UserStatus::Active
  at tests/Unit/Domain/Model/UserTest.php:52
```

---

## Troubleshooting

### "Database not available" in Integration tests
Ensure the DB container is running: `docker compose ps postgres`
If not: `make dev` then retry.

### "SQLSTATE: Relation does not exist"
Migrations haven't run against the test DB: `make db-migrate`

### "Class not found" errors in Integration tests
Run `composer dump-autoload` then retry.

### Behat: "Undefined step"
A step in `.feature` is not implemented in `features/bootstrap/FeatureContext.php`.
Add the step definition.
