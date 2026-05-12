# /quality-check

Run all quality tools and produce a comprehensive report.

## What This Command Does

Execute the following checks **in order** and report ALL issues found:

### Step 1: Domain Purity Check
```bash
grep -rn 'use Symfony\\|use Doctrine\\|use ApiPlatform' src/Domain/
```
Expected: NO output. If any output: list each violation with file path and line number.

### Step 2: PHPStan (Level 8)
```bash
vendor/bin/phpstan analyse --memory-limit=512M --no-progress
```
Report: number of errors, list each error with file:line and message.
Mark as: PASS (0 errors) or FAIL (N errors).

### Step 3: PHP-CS-Fixer (dry-run)
```bash
vendor/bin/php-cs-fixer fix --allow-risky=yes --dry-run --diff 2>&1
```
Report: list all files that would be changed and what changes.
Mark as: PASS (0 files) or FAIL (N files need fixing).

### Step 4: Rector (dry-run)
```bash
vendor/bin/rector process --dry-run --no-progress 2>&1
```
Report: list all suggested refactoring changes.
Mark as: PASS (no suggestions) or ADVISORY (N suggestions — these are improvements, not errors).

### Step 5: Unit Tests
```bash
vendor/bin/pest --testsuite=Unit --no-progress 2>&1
```
Report: PASS/FAIL counts. On failure: list failing test names and error messages.

### Step 6: Unused Dependencies Check (optional)
```bash
# If composer-unused is installed:
composer unused 2>&1
```
Skip if not installed.

---

## Output Format

Produce a summary table at the end:

| Check | Status | Details |
|-------|--------|---------|
| Domain Purity | PASS/FAIL | N violations |
| PHPStan | PASS/FAIL | N errors |
| CS-Fixer | PASS/FAIL | N files |
| Rector | PASS/ADVISORY | N suggestions |
| Unit Tests | PASS/FAIL | N tests, N failures |

**If any check FAILS**: List the specific issues grouped by file. Suggest the fix for each issue.

**If all checks PASS**: Confirm the codebase meets the quality bar and is ready for commit.

---

## How to Fix Common Issues

### PHPStan: "Parameter ... has no type hint"
Add explicit type declarations. Use `mixed` only as a last resort when the type genuinely varies.

### PHPStan: "Access to an undefined property"
Check the property declaration. If you're accessing a Doctrine proxy property, ensure the entity has the field mapped.

### CS-Fixer: "Missing trailing comma"
Run `make cs-fix` to auto-fix.

### Rector: "Could be typed more strictly"
Apply the suggestion: it's an improvement. Run `make rector`.
