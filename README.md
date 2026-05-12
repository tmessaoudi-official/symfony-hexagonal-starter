# Symfony Hexagonal Starter

[![PHP](https://img.shields.io/badge/PHP-8.4-blue.svg)](https://php.net)
[![Symfony](https://img.shields.io/badge/Symfony-7.1-green.svg)](https://symfony.com)
[![API Platform](https://img.shields.io/badge/API_Platform-4.0-orange.svg)](https://api-platform.com)
[![PHPStan](https://img.shields.io/badge/PHPStan-Level_8-brightgreen.svg)](https://phpstan.org)
[![License](https://img.shields.io/badge/License-MIT-lightgrey.svg)](LICENSE)

A production-quality PHP/Symfony/API Platform starter kit built on **Hexagonal Architecture**, **CQRS**, and **Domain-Driven Design**. Every file earns its existence. Every pattern is explained.

---

## Architecture Overview

```
┌─────────────────────────────────────────────────────────────────────────┐
│  INFRASTRUCTURE (Doctrine, Redis, RabbitMQ, Nginx, HTTP, Mailer)        │
│                                                                          │
│  HTTP in ──► StateProcessor ──► CommandBus ──► CommandHandler ──┐       │
│  HTTP in ──► StateProvider  ──► QueryBus   ──► QueryHandler  ──┐│       │
│                                                                 ││       │
│  ┌──────────────────────────────────────────────────────────┐  ││       │
│  │  APPLICATION (Use Cases, Event Handlers, Port Interfaces)│  ││       │
│  │                                                          │  ││       │
│  │  CommandHandler ──► UserRepository (interface) ──────────┼──┼┼──┐   │
│  │  QueryHandler   ──► UserRepository (interface) ──────────┼──┼┘  │   │
│  │  EventHandler   ──► Mailer (interface) ──────────────────┼──┘   │   │
│  │                                                          │       │   │
│  │  ┌─────────────────────────────────────────────────┐    │       │   │
│  │  │  DOMAIN (Aggregates, VOs, Events, Exceptions)   │    │       │   │
│  │  │                                                 │    │       │   │
│  │  │  User ── UserId ── Email ── UserName            │    │       │   │
│  │  │  UserCreated (domain event)                     │    │       │   │
│  │  └─────────────────────────────────────────────────┘    │       │   │
│  └──────────────────────────────────────────────────────────┘       │   │
│                                                                      │   │
│  DoctrineUserRepository (implements interface) ◄─────────────────────┘   │
│  SymfonyMailerAdapter   (implements interface) ◄────────────────────┘   │
│                                                                          │
│  DB out ──► postgres ── Redis ── RabbitMQ                               │
└─────────────────────────────────────────────────────────────────────────┘

Dependency arrows point INWARD ONLY.
Domain knows nothing outside itself.
Infrastructure knows everything but is never imported by Domain.
```

---

## Quick Start

```bash
# 1. Clone and enter
git clone <this-repo> my-project && cd my-project

# 2. One command to rule them all
make setup

# That's it. Your API is running at:
# API:        http://localhost:8080/api
# Docs:       http://localhost:8080/api/docs
# Mailpit:    http://localhost:8025
# Adminer:    http://localhost:8081
# RabbitMQ:   http://localhost:15672  (guest/guest)
```

**Prerequisites**: Docker and Docker Compose. Nothing else.

---

## Why This Structure?

### Why Hexagonal Architecture?

Traditional Symfony applications put Doctrine entities at the centre. Business rules live in service classes that import Doctrine, Symfony events, and HTTP types. The result: unit tests require booting the Symfony kernel. Swapping the ORM requires rewriting 30% of the codebase.

Hexagonal architecture inverts this. The domain is a pure PHP library. It has no idea that Doctrine, Redis, or HTTP exist. You can test every business rule with `vendor/bin/pest --testsuite=Unit` in under a second — no kernel, no database, no containers.

The cost: more files. The benefit: each file has one clear reason to exist.

See [ADR-001](docs/adr/001-hexagonal-architecture.md) for the full decision record.

### Why CQRS?

Write operations (create, update, delete) and read operations (list, get) have different performance characteristics. CQRS separates them at the code level:

- Commands live in `src/Application/Command/`. Handlers return `void`.
- Queries live in `src/Application/Query/`. Handlers return DTOs.

This makes it trivial to add a Redis cache to all queries without touching write logic, or to add a transaction to all commands without touching read logic. It also makes `bin/console messenger:consume async` transparent — commands can be made async without changing their interface.

See [ADR-002](docs/adr/002-cqrs.md) for the full decision record.

### Why Separate Doctrine Entities from Domain Models?

`src/Domain/Model/User.php` and `src/Infrastructure/Persistence/Doctrine/Entity/UserEntity.php` are different classes connected by a `UserMapper`.

Doctrine requires: public setters (for change tracking), no-argument constructors (for proxy creation), and nullable properties (for lazy loading). Domain models require: immutable construction, readonly properties, invariant enforcement in constructors.

These requirements are incompatible. A domain entity that satisfies Doctrine's requirements is not a proper domain entity. The mapper is the deliberate translation layer.

### Why API Platform Resources Are Empty Classes?

`UserResource.php` is an empty class with a `#[ApiResource]` attribute. It's a metadata carrier — API Platform reads the attribute to generate routes and OpenAPI documentation.

The actual data returned by GET is `UserOutput` (a readonly DTO). The data accepted by POST is `CreateUserInput` (a validated DTO). The resource class is pure configuration — it holds no data and no behaviour.

---

## Adding a New Feature

Use `/make-feature FeatureName` in Claude Code for full scaffolding. Manual steps:

**Order matters** — build inward-to-outward:

1. **Domain first** — Value Objects, Aggregate, Repository interface, Domain Events, Exceptions.
   Verify: `grep -rn 'use Symfony\|use Doctrine' src/Domain/` returns nothing.

2. **Application second** — Command + Handler, Query + Handler, Input DTO, Output DTO.
   CommandHandler returns `void`. QueryHandler returns the Output DTO.

3. **Infrastructure last** — Doctrine Entity (separate from Domain), Repository implementation, Mapper, API Platform Resource, StateProcessor, StateProvider.

4. **Tests throughout** — Write the unit test for the domain class before writing the class. Write the integration test before wiring the handler. This is TDD — the tests define the expected behaviour.

5. **Migration** — `bin/console doctrine:migrations:diff` to generate, review, then `make db-migrate`.

6. **DI binding** — Add the repository interface → implementation mapping in `config/services.yaml`.

---

## Real-World Patterns

Each pattern includes the problem, the naive solution (with why it fails), and the expert solution with real PHP code:

| Pattern | Problem | Location |
|---------|---------|----------|
| [N+1 Queries](docs/patterns/n-plus-one.md) | 100 users → 101 queries | DQL JOIN FETCH + separated hydration |
| [Bulk Operations](docs/patterns/bulk-operations.md) | 100k INSERT → OutOfMemoryError | Batch flush + EM clear |
| [Cursor Pagination](docs/patterns/cursor-pagination.md) | OFFSET 50000 → full table scan | WHERE id > :cursor |
| [Optimistic Locking](docs/patterns/optimistic-locking.md) | Two requests → silent data loss | Doctrine @Version + HTTP ETags |
| [Background Jobs](docs/patterns/background-jobs.md) | 10k emails → request timeout | Messenger async + DLQ + retry |
| [Cache Invalidation](docs/patterns/cache-invalidation.md) | Stale cache + stampede | Tag-based Redis + early expiry |

---

## Performance Benchmarks

### Cursor Pagination vs Offset

| Page | OFFSET scan cost | Cursor scan cost |
|------|-----------------|-----------------|
| Page 1 (rows 1-20) | 20 rows | 20 rows |
| Page 100 (rows 1981-2000) | 2,000 rows | 20 rows |
| Page 1000 (rows 19981-20000) | 20,000 rows | 20 rows |
| Page 50000 (rows 999981-1000000) | 1,000,000 rows | 20 rows |

At 1 million rows, cursor pagination is **~50,000x faster** for deep pages.
See [cursor-pagination.md](docs/patterns/cursor-pagination.md).

### N+1 Fix Impact

| Approach | Queries for 100 users | Time (typical) |
|----------|----------------------|----------------|
| Naive (N+1) | 101 | ~101ms |
| JOIN FETCH (DQL) | 1 | ~3ms |
| Separated hydration (2 queries) | 2 | ~4ms |

See [n-plus-one.md](docs/patterns/n-plus-one.md).

---

## Available Services

| Service | URL | Notes |
|---------|-----|-------|
| API | http://localhost:8080/api | PHP-FPM behind Nginx |
| API Docs (OpenAPI) | http://localhost:8080/api/docs | Auto-generated by API Platform |
| Mailpit | http://localhost:8025 | Catches all outgoing email |
| Adminer | http://localhost:8081 | PostgreSQL UI |
| RabbitMQ UI | http://localhost:15672 | guest/guest |

---

## Development Commands

```bash
make test          # All tests
make test-unit     # Domain tests (no DB, ~0.5s)
make analyze       # PHPStan level 8
make cs-fix        # Fix code style
make rector        # Apply Rector refactoring
make mutation      # Infection mutation testing (MSI target: 80%)
make coverage      # HTML coverage report → build/coverage/
make db-migrate    # Run pending migrations
make db-reset      # Destroy and recreate DB (dev only)
make worker        # Start Messenger worker in foreground
```

---

## License

MIT
