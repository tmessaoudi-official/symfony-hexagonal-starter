# CLAUDE.md — Symfony Hexagonal Starter

## Architecture Principles

### Hexagonal Architecture (Ports & Adapters)
The domain is at the centre. No PHP file in `src/Domain/` may import from `Symfony\`, `Doctrine\`, or `ApiPlatform\`. Before editing any Domain class, verify: `grep -rn 'use Symfony\|use Doctrine\|use ApiPlatform' src/Domain/` must return nothing.

- **Domain** (`src/Domain/`): Pure PHP. Entities, Value Objects, domain events, repository interfaces, domain exceptions. Zero framework dependencies.
- **Application** (`src/Application/`): Orchestrates the domain. Commands, Queries, Handlers, Event Handlers, Input/Output DTOs. May use Symfony Validator attributes on Input DTOs. No HTTP, no Doctrine, no API Platform.
- **Infrastructure** (`src/Infrastructure/`): Adapts the outside world. Doctrine entities, repositories, API Platform resources, processors, providers, mailer adapters. Imports Symfony and Doctrine freely.

### CQRS
- **Commands** (`src/Application/Command/`): Change state. Handlers return `void`. One directory per command (`CreateUser/CreateUserCommand.php` + `CreateUser/CreateUserCommandHandler.php`).
- **Queries** (`src/Application/Query/`): Read state. Handlers return DTOs. Must not modify state.
- **Event Handlers** (`src/Application/EventHandler/`): React to domain events via Messenger.

### Dependency Inversion
Repository interfaces live in `src/Domain/Repository/`. Implementations live in `src/Infrastructure/Persistence/`. Bindings are in `config/services.yaml` under "Port Bindings".

Port interfaces live in `src/Application/Port/`. Implementations live in `src/Infrastructure/`.

---

## How to Add a New Feature

Use `/make-feature FeatureName` for full scaffolding. Manual steps:

### 1. Domain Layer (`src/Domain/`)
- Create the aggregate in `src/Domain/Model/FeatureName.php`
- Create Value Objects in `src/Domain/Model/FeatureNameId.php`, etc.
- Create domain events in `src/Domain/Event/FeatureNameCreated.php`
- Create repository interface in `src/Domain/Repository/FeatureNameRepository.php`
- Create domain exceptions in `src/Domain/Exception/`

### 2. Application Layer (`src/Application/`)
- Create `Command/CreateFeatureName/CreateFeatureNameCommand.php` + Handler
- Create `Query/GetFeatureName/GetFeatureNameQuery.php` + Handler
- Create `DTO/Input/CreateFeatureNameInput.php` (with Validator constraints)
- Create `DTO/Output/FeatureNameOutput.php` (readonly)

### 3. Infrastructure Layer (`src/Infrastructure/`)
- Create `Persistence/Doctrine/Entity/FeatureNameEntity.php` (Doctrine entity, NOT the domain model)
- Create `Persistence/Doctrine/Repository/DoctrineFeatureNameRepository.php`
- Create `Persistence/Doctrine/Repository/FeatureNameMapper.php`
- Create `Http/Resource/FeatureNameResource.php` (API Platform resource)
- Create `Http/StateProcessor/CreateFeatureNameProcessor.php`
- Create `Http/StateProvider/FeatureNameProvider.php`

### 4. Tests
- `tests/Unit/Domain/Model/FeatureNameTest.php` — pure domain logic, no framework. Write it as a PHPUnit class (`final class FeatureNameTest extends TestCase`), not Pest closures: Infection mutation-tests `src/Domain` and can only drive PHPUnit classes (see `docs/testing.md`)
- `tests/Integration/Application/CreateFeatureNameCommandHandlerTest.php` — real DB, DAMA rollback
- `features/feature_name.feature` — Behat E2E scenarios

### 5. Migration
- Run `bin/console doctrine:migrations:diff` to generate the migration
- Review and commit the generated migration file

### 6. DI Bindings
- Add the repository binding to `config/services.yaml` under "Port Bindings"

---

## Common Gotchas

### EntityManager Clearing in Batches
When processing large datasets, call `$em->flush(); $em->clear();` every N items (see `docs/patterns/bulk-operations.md`). After `clear()`, any previously loaded entities become detached — calling methods on them or passing them to `flush()` will throw. Re-fetch entities after a `clear()` if you need them.

### Doctrine Lazy Objects (no proxy classes)
Doctrine ORM 3 on PHP 8.4+ uses native lazy objects: a reference (`$em->getReference()`) or a lazy association is an uninitialised instance of the entity class itself, not a generated proxy subclass. `get_class($entity)` returns `UserEntity`; `(new \ReflectionClass($entity))->isUninitializedLazyObject($entity)` tells whether it is still lazy. There is no proxy directory to configure or warm.

### Messenger Serialization
Messages dispatched to async transports (RabbitMQ) are serialized. Only use serializable types in your Command/Event/Query classes: strings, ints, arrays, scalars. Value Objects (Email, UserId) are not serializable by default — either serialize primitives and reconstruct VOs in the handler, or add a Messenger serializer for your domain types.

### Doctrine Enum Mapping
Backed enums (`UserStatus`) are mapped via `enumType` in `#[ORM\Column]`. Doctrine 3 handles this natively. If you add a new enum case, add it to the DB CHECK constraint and run a migration.

### Domain Events in Tests
`User::create()` records a `UserCreated` event internally. If you call `create()` in tests without pulling events (`$user->pullDomainEvents()`), the events accumulate. In integration tests, the command handler pulls and dispatches them — so the Messenger `in-memory` transport receives them. Flush the transport after the handler if you need to assert on events.

### DAMA DoctrineTestBundle
Integration tests (in `tests/Integration/`) run inside a rolled-back transaction. This means:
- You can INSERT, UPDATE, DELETE freely — everything is undone after the test.
- `$em->flush()` works normally inside the test.
- `SELECT` queries see the data written in the same test.
- Sequences and auto-increment counters are NOT rolled back (they advance permanently). Use UUID PKs to avoid this.

---

## Available Commands

### Development
```bash
make setup          # First-time setup (build, install, migrate)
make dev            # Start all Docker services
make down           # Stop all services
make shell          # Shell into the app container
make logs           # Tail app logs
```

### Testing
```bash
make test           # All tests
make test-unit      # Domain unit tests (fast, no DB)
make test-integration  # Integration tests (requires DB)
make test-e2e       # Behat API scenarios
make test-functional  # HTTP-level API tests (requires DB)
make coverage       # HTML coverage report in build/coverage/
make mutation       # Infection on src/Domain + Pest --mutate on src/Application
```

### Message handlers
Always name the bus: `#[AsMessageHandler(bus: 'command.bus')]`. Without `bus:` a handler is registered on every bus. Inject the event bus as `MessageBusInterface $eventBus` (the parameter name selects `event.bus`); a plain `$bus` gets the default `command.bus`, which rejects events that have no handler.

### Quality
```bash
make analyze        # PHPStan level 8
make cs-check       # PHP-CS-Fixer dry-run
make cs-fix         # PHP-CS-Fixer apply
make rector-dry     # Rector suggestions (no write)
make rector         # Apply Rector refactoring
```

### Database
```bash
make db-migrate     # Run migrations
make db-test        # Create + migrate app_test; test targets override APP_ENV and DATABASE_URL (see Makefile)
make db-reset       # Drop, recreate, migrate (WARNING: destroys data)
```

### Slash Commands (Claude Code)
- `/make-feature <Name>` — scaffold a complete feature
- `/quality-check` — run all quality tools and report issues
- `/run-tests` — run the full test suite and report results
