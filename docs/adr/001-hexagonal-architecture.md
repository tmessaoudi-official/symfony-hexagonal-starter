# ADR-001: Hexagonal Architecture (Ports & Adapters)

**Status**: Accepted
**Date**: 2026-01-01
**Deciders**: Architecture team

---

## Context

This is a production API that will be maintained and extended for years. We need an architecture that:

1. Can be tested independently of HTTP, databases, and message brokers.
2. Allows infrastructure components (Doctrine, Redis, RabbitMQ, Mailer) to be swapped without rewriting business logic.
3. Makes the system's intent clear at a glance — new developers should be able to find where to add business rules, where to add HTTP endpoints, and where to add database queries.

The naive approach is a traditional Symfony "fat controller" or "fat service" architecture, where everything is layered on top of Doctrine entities and Symfony services. It works for small projects but creates tight coupling that becomes painful at scale.

---

## Decision

We adopt **Hexagonal Architecture** (also called Ports & Adapters, introduced by Alistair Cockburn in 2005).

### The Three Layers

```
┌─────────────────────────────────────────────────────────────┐
│                    Infrastructure                            │
│  (Doctrine, Redis, RabbitMQ, Nginx, HTTP, Mailer)           │
│                                                              │
│    ┌───────────────────────────────────────────────────┐    │
│    │                  Application                      │    │
│    │  (Use Cases: Commands, Queries, Event Handlers)   │    │
│    │                                                   │    │
│    │    ┌─────────────────────────────────────────┐   │    │
│    │    │              Domain                     │   │    │
│    │    │  (Entities, Value Objects, Events,      │   │    │
│    │    │   Aggregates, Repository Interfaces)    │   │    │
│    │    └─────────────────────────────────────────┘   │    │
│    └───────────────────────────────────────────────────┘    │
└─────────────────────────────────────────────────────────────┘
```

**Dependency rule**: arrows always point INWARD. Infrastructure knows Application. Application knows Domain. Domain knows nothing outside itself.

### Ports (Interfaces)

- `App\Domain\Repository\UserRepository` — the domain declares what it needs from persistence.
- `App\Application\Port\Mailer` — the application declares what it needs from email sending.

### Adapters (Implementations)

- `App\Infrastructure\Persistence\Doctrine\Repository\DoctrineUserRepository` — the Doctrine implementation of `UserRepository`.
- `App\Infrastructure\Messaging\SymfonyMailerAdapter` — the Symfony Mailer implementation of `Mailer`.

---

## Consequences

### Positive

- **Testability**: Unit tests for the Domain and Application layers need no framework, no database, no mocks of Symfony internals. They run in milliseconds.
- **Replaceability**: Replace Doctrine with another ORM (or raw PDO) by writing one new adapter class.
- **Clarity**: `ls src/Domain/` shows you the business vocabulary. `ls src/Infrastructure/` shows you the technical dependencies.

### Negative

- **More files**: A simple CRUD feature requires ~10 files instead of ~3. This is the cost of explicit design.
- **Mapping overhead**: Domain entities and Doctrine entities are separate classes connected by a Mapper. This is intentional — see ADR-001 rationale above.
- **Learning curve**: Developers unfamiliar with hexagonal architecture need onboarding. The `docs/patterns/` files help.

---

## Alternatives Considered

| Alternative | Why rejected |
|-------------|-------------|
| Traditional layered (Controller → Service → Repository with Doctrine entities as the model) | Works fine at small scale. Becomes an unmaintainable ball of mud when domain logic leaks into services and controllers. Infrastructure concerns (Doctrine lazy loading, Symfony Events) pollute business rules. |
| Clean Architecture (Uncle Bob variant) | Hexagonal achieves the same goals with less ceremony. Clean Architecture's "use case" ring is equivalent to our Application layer; its "interface adapters" ring is our Infrastructure adapters. We use the simpler vocabulary. |
| CQRS with Event Sourcing | Event Sourcing is a powerful pattern but adds significant complexity (event store, projections, snapshot management). Our current requirements don't justify it. We use CQRS (see ADR-002) without Event Sourcing — the two are independent. |
