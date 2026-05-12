# Pattern: Bulk Operations

## The Problem

You need to import 100,000 users from a CSV file.

**Naive approach:**

```php
foreach ($csvRows as $row) {
    $user = User::create(
        UserId::generate(),
        Email::fromString($row['email']),
        UserName::fromString($row['name']),
    );
    $this->users->save($user); // calls em->flush() every time
}
```

**What happens:**
- `em->flush()` sends an INSERT to the DB for every row: 100,000 round-trips.
- Doctrine's Unit of Work accumulates ALL 100,000 entities in memory before the loop ends.
- At ~500 bytes per entity: 50MB just for entities. Plus identity map overhead. You hit PHP's `memory_limit` at ~20,000 rows.
- Total time: ~5 minutes for a fast DB. Often exceeds PHP `max_execution_time`.

---

## Solution: Batch Flush with EntityManager::clear()

```php
final class ImportUsersCommand
{
    private const BATCH_SIZE = 500;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserMapper $mapper,
    ) {}

    public function execute(iterable $rows): ImportResult
    {
        $inserted = 0;
        $errors   = [];
        $i        = 0;

        // Disable SQL logger — it stores every query string in memory by default.
        // In Doctrine 3.x, logging is handled differently; this is the DBAL approach:
        $this->em->getConnection()->getConfiguration()->setSQLLogger(null);

        foreach ($rows as $row) {
            try {
                $entity = new UserEntity(
                    id: UserId::generate()->value(),
                    email: strtolower(trim($row['email'])),
                    name: trim($row['name']),
                    status: UserStatus::Active,
                    createdAt: new DateTimeImmutable(),
                    updatedAt: new DateTimeImmutable(),
                );

                $this->em->persist($entity);
                $inserted++;
                $i++;

                // Flush and clear every BATCH_SIZE rows.
                // flush(): sends accumulated INSERTs to the DB in one transaction.
                // clear(): releases all managed entities from memory.
                if ($i % self::BATCH_SIZE === 0) {
                    $this->em->flush();
                    $this->em->clear();

                    // Optional: log progress
                    // $this->logger->info("Imported {$inserted} users...");
                }
            } catch (\Exception $e) {
                $errors[] = ['row' => $row, 'error' => $e->getMessage()];
            }
        }

        // Flush remaining entities (the last partial batch).
        if ($i % self::BATCH_SIZE !== 0) {
            $this->em->flush();
            $this->em->clear();
        }

        return new ImportResult(inserted: $inserted, errors: $errors);
    }
}
```

**What changes:**
- Memory stays constant: at most BATCH_SIZE entities live in the identity map at once.
- 100,000 rows / 500 per batch = 200 `flush()` calls. Each flush sends 500 INSERTs in a single statement batch. ~5 seconds total vs 5 minutes.
- SQL logger is disabled to prevent log accumulation eating memory.

---

## Why BATCH_SIZE = 500?

- Too small (e.g. 10): too many DB round-trips.
- Too large (e.g. 10,000): too many entities in memory before a flush.
- 500 is empirically fast for PostgreSQL with typical row sizes. Profile with your actual data to tune.
- For very wide rows (many columns, large text fields), lower to 100–200.

---

## Transactions and Atomicity

The pattern above commits every 500 rows. If row 750 fails, rows 1–500 are already committed. This is intentional for imports — you want partial progress preserved, not an all-or-nothing rollback that forces you to rerun the whole file.

If you need all-or-nothing atomicity:

```php
$this->em->getConnection()->beginTransaction();
try {
    // ... the loop above, without individual flush()
    $this->em->flush();
    $this->em->getConnection()->commit();
} catch (\Exception $e) {
    $this->em->getConnection()->rollBack();
    throw $e;
}
```

**Caution**: A 100k-row transaction holds locks on all inserted rows until commit. For PostgreSQL, this is typically fine. For MySQL with InnoDB, large transactions can cause lock wait timeouts.

---

## Alternative: DBAL Insert (bypasses ORM entirely)

For maximum throughput (millions of rows), bypass Doctrine ORM and use DBAL directly:

```php
$connection = $this->em->getConnection();
$connection->beginTransaction();

$i = 0;
$stmt = $connection->prepare(
    'INSERT INTO users (id, email, name, status, created_at, updated_at)
     VALUES (:id, :email, :name, :status, :created_at, :updated_at)
     ON CONFLICT (email) DO NOTHING'
);

foreach ($rows as $row) {
    $now = new DateTimeImmutable();
    $stmt->executeStatement([
        'id'         => UserId::generate()->value(),
        'email'      => strtolower(trim($row['email'])),
        'name'       => trim($row['name']),
        'status'     => 'active',
        'created_at' => $now->format('Y-m-d H:i:s'),
        'updated_at' => $now->format('Y-m-d H:i:s'),
    ]);

    if (++$i % 500 === 0) {
        $connection->commit();
        $connection->beginTransaction();
    }
}

$connection->commit();
```

**Tradeoff**: No ORM mapping, no domain events, no lifecycle callbacks. Use for data migrations and seeding only — not for business-critical writes where domain invariants must be enforced.
