# ADR-002: CQRS (Command Query Responsibility Segregation)

**Status**: Accepted
**Date**: 2026-01-01
**Deciders**: Architecture team

---

## Context

As the application grows, read and write workloads have different characteristics:

- **Writes** are infrequent, need strong consistency, and benefit from transactional guarantees.
- **Reads** are frequent, can tolerate slight staleness, and benefit from denormalised or cached views.

Traditional service classes that mix read and write operations create a single coupling point. Adding a new field to the list view requires modifying the same service that handles creation — increasing the risk of accidental regressions.

---

## Decision

We apply **CQRS** at the Application layer:

- **Commands** (`CreateUserCommand`, etc.) represent the intent to CHANGE STATE. Handlers return `void`. If you need to know the result of a command, query it afterwards.
- **Queries** (`GetUserQuery`, `ListUsersQuery`, etc.) represent the intent to READ STATE. Handlers return DTOs. Handlers must not modify any state.

This separation is enforced by convention and by the Symfony Messenger bus configuration:
- Commands go through `command.bus` (with `doctrine_transaction` middleware).
- Queries go through `query.bus` (no transaction overhead).
- Domain events go through `event.bus`.

---

## Why CommandHandlers Return void

This is the most frequently questioned decision. The argument for returning data: "I need the new user's ID after creation."

The counter-argument:
1. If the caller needs the ID, it should generate it BEFORE dispatching the command and pass it in. This is idempotency by design.
2. If the caller generates the ID, the handler can be made async (dispatched to a queue worker) without breaking the caller.
3. Returning data from a command mixes concerns and breaks the async path.

In this starter, the `CreateUserProcessor` works around this by looking up the user by email after creation. In a more robust system, the client generates the ID (UUID) before calling POST.

---

## Consequences

### Positive

- **Independent scaling**: Read models can be moved to a read replica, ElasticSearch, or Redis without touching write logic.
- **Explicit intent**: `CreateUserCommand` is unmistakably a write operation. `ListUsersQuery` is unmistakably a read operation. No ambiguity.
- **Middleware**: Transaction middleware applies only to commands. Rate limiting can apply only to writes. Caching middleware can apply only to queries.
- **Async-ready**: Commands dispatched via Messenger can trivially be moved to async transports without changing the command class.

### Negative

- **More classes**: Each use case produces 2 classes (Command + Handler or Query + Handler) plus a DTO. In exchange, each class is small and single-purpose.
- **No return value from commands**: New developers expecting `$id = $bus->dispatch(new CreateUserCommand(...))` need to understand the pattern.

---

## Alternatives Considered

| Alternative | Why rejected |
|-------------|-------------|
| Traditional service classes (UserService::create(), UserService::findAll()) | Easier to start with, but services accumulate responsibilities over time. Dependencies (email, cache, events) all pile into the same class. |
| Direct repository calls in controllers | No application layer = no place to put cross-cutting concerns (validation, events, transactions). Business logic leaks into controllers. |
| CQRS with separate read DB | More powerful (read models can be fully denormalised), but requires infrastructure for dual writes and eventual consistency. Deferred until there's a measured need. |
