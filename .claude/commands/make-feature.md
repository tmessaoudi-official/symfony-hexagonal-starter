# /make-feature

Scaffold a complete Hexagonal Architecture feature given a name.

## Usage

```
/make-feature FeatureName
```

Example: `/make-feature Product` creates the full Product feature.

## What This Command Does

Given `$NAME` (e.g. "Product"), scaffold ALL of the following files:

### 1. Domain Layer

**`src/Domain/Model/${NAME}Id.php`**
- Readonly VO wrapping a UUID string
- `fromString(string): self` + `generate(): self` + `value(): string` + `equals(self): bool` + `__toString(): string`

**`src/Domain/Model/${NAME}.php`**
- Aggregate Root with private constructor
- `create(${NAME}Id, ...)` factory method that records a `${NAME}Created` domain event
- `reconstitute(...)` factory method that does NOT record events
- `pullDomainEvents(): list<object>` method
- Accessors for all fields

**`src/Domain/Event/${NAME}Created.php`**
- Readonly POPO with `occurredAt: DateTimeImmutable` auto-set in constructor

**`src/Domain/Exception/${NAME}NotFoundException.php`**
- Extends RuntimeException
- `withId(${NAME}Id): self` named constructor

**`src/Domain/Repository/${NAME}Repository.php`**
- Interface with: `save(${NAME}): void`, `getById(${NAME}Id): ${NAME}`, `findById(${NAME}Id): ?${NAME}`, `findAll(): list<${NAME}>`, `findPage(int, ?${NAME}Id): list<${NAME}>`, `delete(${NAME}): void`

### 2. Application Layer

**`src/Application/Command/Create${NAME}/Create${NAME}Command.php`**
- Readonly with string primitive fields

**`src/Application/Command/Create${NAME}/Create${NAME}CommandHandler.php`**
- Injects `${NAME}Repository` and `MessageBusInterface`
- Returns void
- Pulls and dispatches domain events

**`src/Application/Query/Get${NAME}/Get${NAME}Query.php`**
- Readonly with `${lcName}Id: string`

**`src/Application/Query/Get${NAME}/Get${NAME}QueryHandler.php`**
- Returns `${NAME}Output`

**`src/Application/Query/List${NAME}s/List${NAME}sQuery.php`**
- Cursor pagination query

**`src/Application/Query/List${NAME}s/List${NAME}sQueryHandler.php`**
- Returns `${NAME}ListOutput`

**`src/Application/DTO/Input/Create${NAME}Input.php`**
- Readonly with Symfony Validator constraints

**`src/Application/DTO/Output/${NAME}Output.php`**
- Final readonly class with all fields as primitives + DateTimeImmutable

### 3. Infrastructure Layer

**`src/Infrastructure/Persistence/Doctrine/Entity/${NAME}Entity.php`**
- Doctrine ORM entity with `#[ORM\Entity]`, `#[ORM\Table]`
- Separate from the Domain model

**`src/Infrastructure/Persistence/Doctrine/Repository/${NAME}Mapper.php`**
- `toEntity(${NAME}): ${NAME}Entity`
- `updateEntity(${NAME}Entity, ${NAME}): void`
- `toDomain(${NAME}Entity): ${NAME}`

**`src/Infrastructure/Persistence/Doctrine/Repository/Doctrine${NAME}Repository.php`**
- Implements `${NAME}Repository`
- Uses `${NAME}Mapper`
- Implements cursor pagination in `findPage()`

**`src/Infrastructure/Http/Resource/${NAME}Resource.php`**
- API Platform `#[ApiResource]` with `Post`, `Get`, `GetCollection` operations

**`src/Infrastructure/Http/StateProcessor/Create${NAME}Processor.php`**
- Implements `ProcessorInterface`
- Dispatches `Create${NAME}Command`
- Returns `${NAME}Output`

**`src/Infrastructure/Http/StateProvider/${NAME}Provider.php`**
- Implements `ProviderInterface`
- Routes to `Get${NAME}QueryHandler` or `List${NAME}sQueryHandler`

### 4. Tests

**`tests/Unit/Domain/Model/${NAME}Test.php`**
- Pest describe block
- Tests: create records event, reconstitute does not, deactivate/activate lifecycle

**`tests/Integration/Application/Create${NAME}CommandHandlerTest.php`**
- KernelTestCase-based (via Pest.php)
- Tests: creates and persists, throws on duplicate

**`features/${lcName}.feature`**
- Behat scenarios: POST success (201), invalid input (422), duplicate (409), GET (200), GET unknown (404), list with pagination

### 5. Migration

**Print (don't create)** the migration command to run:
```bash
bin/console doctrine:migrations:diff
# Review the generated migration, then:
bin/console doctrine:migrations:migrate --no-interaction
```

### 6. DI Binding

**Print (don't create)** the YAML snippet to add to `config/services.yaml`:
```yaml
App\Domain\Repository\${NAME}Repository:
    alias: App\Infrastructure\Persistence\Doctrine\Repository\Doctrine${NAME}Repository
```

---

## Quality Checklist After Scaffolding

After creating the files:
1. Run `grep -rn 'use Symfony\|use Doctrine\|use ApiPlatform' src/Domain/` — must return empty.
2. Run `make analyze` — PHPStan must pass.
3. Run `make test-unit` — new unit tests must pass.
4. Run the migration and `make test-integration`.

## Notes

- Follow all patterns from the User feature exactly.
- `declare(strict_types=1);` at the top of every PHP file.
- All DTOs must be `final readonly`.
- CommandHandler returns `void`, QueryHandler returns a DTO.
- No magic strings — use enums for status values.
