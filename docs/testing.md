# Testing

| Suite | Path | Needs | Runs with |
|---|---|---|---|
| Unit | `tests/Unit/` | nothing (pure PHP) | `vendor/bin/pest --testsuite=Unit` |
| Domain | `tests/Unit/Domain/` (subset of Unit) | nothing | `vendor/bin/pest --testsuite=Domain` (used by Infection) |
| Integration | `tests/Integration/` | PostgreSQL (DAMA rollback per test) | `vendor/bin/pest --testsuite=Integration` |
| Functional | `tests/Functional/` | PostgreSQL | `vendor/bin/pest --testsuite=Functional` |
| E2E | `features/` | PostgreSQL | `vendor/bin/behat` |

A plain `vendor/bin/pest` runs Unit, Integration and Functional (`defaultTestSuite` in
`phpunit.xml.dist`). "Domain" is left out of that default because its files are also in
"Unit": loading both in one run is a PHPUnit warning, and `failOnWarning` fails the build.

Test doubles: prefer the real thing or an in-memory adapter (`tests/Support/InMemoryUserRepository.php`)
over mocks. Mock only ports whose effect you assert (the event bus, the mailer).

One rule, two enforcement points. The Domain value objects (`Email`, `UserName`) own the rules and
refuse invalid state whatever the entry point (CLI, import job, test). The input DTO
(`CreateUserInput`) runs those same value objects in an `Assert\Callback` and turns a refusal into a
422 with a violation on the field. It does not restate them with `Assert\Email` / `Assert\Length`,
because a restated rule drifts: measured here, both `Assert\Email` modes accepted addresses the
Domain refuses (`a..b@example.com`, a 65-character local part, `é@example.com`, `a@b.123`), and
`Assert\Length` counts characters where `UserName` counts bytes. Each of those reached the Domain
and came back as a 500. `testEveryInputTheDomainRejectsIsA422NotA500` pins them. The OpenAPI
hints API Platform used to derive from those attributes (`format: email`, the lengths) are now
declared as `ApiProperty` schemas: documentation for clients, enforcing nothing, pinned by
`testTheOpenApiContractKeepsTheInputHints`. They are approximate by nature: JSON Schema counts
characters where `UserName` counts bytes, and `format: email` is looser than the Domain. The server
answer (422 or 201) is the authority. The read side follows the same rule: a malformed `after`
cursor (a non-UUID, or an array) is checked with `UserId` in the provider and returns 400, and an
out-of-range `limit` is clamped to 1..100.

## Mutation testing: two tools, one per layer

Code coverage says a line *ran*. Mutation testing says whether a test would *notice* if that
line were wrong: the tool changes the code (`>` becomes `>=`, a call is removed, a `true` becomes
`false`), reruns the tests that cover it, and counts the mutants no test kills.

| Layer | Tool | Why this tool here | Config | Floor |
|---|---|---|---|---|
| `src/Domain` | [Infection](https://infection.github.io) | The reference PHP mutation tool: many mutators, HTML report, per-mutant diffs | `infection.json5` (`minMsi`, `minCoveredMsi`) | 88% |
| `src/Application` | [Pest `--mutate`](https://pestphp.com/docs/mutation-testing) | Built into Pest, runs on the Pest closures used for handler tests | `--class='App\Application' --min=98` in CI | 98% |

**Why Domain tests are PHPUnit classes.** Infection 0.35 drives PHPUnit, phpspec and Codeception,
but not Pest closures: on this suite's Pest files its initial test run fails. Pest runs PHPUnit
classes natively, so `tests/Unit/Domain/` is written as `final class ... extends TestCase` and
counts in the normal Unit run too. No test is duplicated.

**Floors come from measured runs** (2026-11-25, PHP 8.5, pcov), not from a round number, and
leave one or two mutants of margin so that new untested code fails the build:

- Infection on `src/Domain`: 116 mutants, 105 killed, 0 uncovered, MSI 90%, identical over
  repeated runs. The 11 survivors are equivalent mutants, changes no test can observe:
  `UserId::generate()` reading a different or an extra random byte, or fixing one random bit to
  0 or 1 (the output is still a valid UUID v4, with one bit less entropy at worst), an `(int)` cast on a value that is already an int,
  and a `match` arm the policy can never reach (demotions are rejected before it).
- Pest on `src/Application`: 51 mutations, all killed (100%), identical over two parallel runs.
  The floor of 98 leaves one mutant of margin.
  Two lines carry `// @pest-mutate-ignore`: the `ApiProperty` schema hints of `CreateUserInput`.
  pcov records no coverage for attribute arguments, so their 12 mutants could only ever count as
  uncovered; a reflection Unit test and the OpenAPI Functional test pin them instead. Never add
  the comment to a line a test can reach.

Raise a floor when you kill survivors; never lower one to make a build pass.

### What it found here

Mutation runs on this repo surfaced real gaps that line coverage reported as covered:

- `Email`: changing `> 254` to `>= 254` survived, because no test used an address of exactly
  254 characters. A boundary test now does.
- `UserId`: removing the `^` or `$` anchor of the UUID pattern survived; tests now reject
  characters before and after a valid UUID.
- `UserId::generate()`: the test checked one random UUID, so a broken version or variant mask
  was caught only when the random bytes happened to expose it, and the score moved between runs.
  The test now checks 256 UUIDs, which makes it deterministic in practice.
- `UserPromotionPolicy`: `<` to `<=` on the minimum account age survived; an account exactly 30
  (or 90) days old is now tested.
- `User::pullDomainEvents()`: returning only the first event survived; a test now records two.
- `ListUsersQuery` and `ListUsersQueryHandler`: the limits 1 and 100, the default page size, and
  which row is dropped when there is a next page were untested.
- `SendWelcomeEmailOnUserCreated`: deleting the log call survived; a test now asserts it.
- `PromoteUserCommandHandler` had no test at all. Writing one exposed a wiring bug: the handler
  sent its events to the command bus, which rejects events that have no handler.
- `PromoteUserCommandHandler`: removing `save()` survived, because the in-memory repository handed
  back the very object the handler changed, so an unsaved change looked persisted. The fake now
  stores and returns clones, like the Doctrine adapter whose mapper builds a new `User` per read.
- `CreateUserInput`: its length limits showed as uncovered. Testing the boundaries through the API
  led to inputs that passed the DTO and failed in the Domain as a 500 (see "One rule, two
  enforcement points" above); the DTO now asks the value objects and has its own Unit test.

### Run it

```bash
make mutation          # both tools, with their floors
vendor/bin/infection --threads=4 --no-progress                         # Domain; report in build/infection/
vendor/bin/pest --testsuite=Unit --mutate --class='App\Application' --parallel --min=98
```

Both need a coverage driver (pcov in the `dev` and `test` images).
