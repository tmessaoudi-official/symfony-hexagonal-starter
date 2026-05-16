# Contributing to Symfony Hexagonal Starter

## Prerequisites

- PHP 8.4
- Composer 2
- Docker & Docker Compose (for PostgreSQL and integration tests)
- Make

## Getting started

```bash
git clone https://github.com/takieddine-messaoudi/symfony-hexagonal-starter
cd symfony-hexagonal-starter
make setup   # builds containers, installs deps, runs migrations
make dev     # starts all services
```

## Development workflow

```bash
make test-unit        # Domain unit tests (pure PHP, no DB)
make test-integration # Integration tests with real DB (DAMA rollback)
make test-e2e         # Behat API scenarios
make test             # All tests
make analyze          # PHPStan level 8
make cs-fix           # PHP-CS-Fixer apply
make mutation         # Infection mutation testing
```

## Architecture rules

The core invariant: **no file in `src/Domain/` may import from `Symfony\`, `Doctrine\`, or `ApiPlatform\`**.

Verify any time you edit the domain layer:
```bash
grep -rn 'use Symfony\|use Doctrine\|use ApiPlatform' src/Domain/
# Must return nothing
```

## Adding a feature

See [CONTRIBUTING.md](CONTRIBUTING.md) (this file) for the scaffold guide — build inward-to-outward:

1. **Domain** — Value Objects, Aggregate, Repository interface, Domain Events, Exceptions
2. **Application** — Command + Handler, Query + Handler, Input/Output DTOs
3. **Infrastructure** — Doctrine Entity, Repository impl, API Platform Resource, StateProcessor, StateProvider
4. **Migration** — `bin/console doctrine:migrations:diff`
5. **DI binding** — `config/services.yaml` under "Port Bindings"

## Code style

- PSR-12 + Symfony coding standards (PHP-CS-Fixer)
- Rector for automated refactoring to modern PHP 8.4
- Readonly properties by default for Value Objects and DTOs

## Pull requests

1. Fork and create a branch (`feat/<name>`)
2. Run `make test && make analyze` — all must pass
3. Integration tests use DAMA rollback — no DB cleanup needed in test teardown
4. Open a PR describing the feature, its test coverage, and any new architecture patterns
