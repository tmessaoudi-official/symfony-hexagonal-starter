# Pattern: Cursor-Based Pagination

## The Problem

**Offset pagination:**

```sql
SELECT * FROM users ORDER BY created_at DESC LIMIT 20 OFFSET 10000;
```

**What happens internally:**
1. PostgreSQL scans and loads 10,020 rows.
2. Discards the first 10,000.
3. Returns 20.

For `OFFSET 100000 LIMIT 20`: PostgreSQL scans 100,020 rows. Every time. Even with an index on `created_at`, the DB must count rows to skip them.

**Additional problems:**
- Concurrent inserts between page requests cause rows to appear on two pages or disappear entirely (the "page drift" problem).
- Page numbers in URLs are meaningless — "page 5" changes meaning as data is added.

---

## Solution: Cursor-Based Pagination

Instead of "skip N rows", ask for "rows after this specific row".

**The query:**

```sql
-- First page (no cursor)
SELECT * FROM users ORDER BY id ASC LIMIT 21;

-- Subsequent pages (cursor = last seen id)
SELECT * FROM users WHERE id > '550e8400-e29b-41d4-a716-446655440000'
ORDER BY id ASC LIMIT 21;
```

**Why 21 when the page size is 20?**
- Fetch `limit + 1` rows.
- If you get 21 back: there IS a next page. Drop the 21st row from the response. The 20th row's ID becomes the `nextCursor`.
- If you get ≤ 20 back: you're on the last page. `hasMore = false`, `nextCursor = null`.

This trick avoids a separate COUNT query (which is expensive) to determine `hasMore`.

---

## Implementation: CursorPaginator Value Object

```php
final readonly class CursorPage
{
    /**
     * @param list<UserOutput> $items
     */
    public function __construct(
        public readonly array $items,
        public readonly ?string $nextCursor,
        public readonly bool $hasMore,
    ) {}
}
```

**In the repository (already implemented in `DoctrineUserRepository::findPage`):**

```php
public function findPage(int $limit, ?UserId $cursor = null): array
{
    $qb = $this->em->createQueryBuilder()
        ->select('u')
        ->from(UserEntity::class, 'u')
        ->orderBy('u.id', 'ASC')
        ->setMaxResults($limit);  // Note: this is limit+1 from the handler

    if ($cursor !== null) {
        $qb->where('u.id > :cursor')
           ->setParameter('cursor', $cursor->value());
    }

    return $qb->getQuery()->getResult();
}
```

**In the query handler (`ListUsersQueryHandler`):**

```php
// Fetch one extra item to detect "has more"
$users = $this->users->findPage($query->limit + 1, $cursor);

$hasMore = count($users) > $query->limit;
if ($hasMore) {
    array_pop($users); // Remove the sentinel item
}

$nextCursor = $hasMore ? $items[array_key_last($items)]->id : null;
```

---

## API Usage

**First page:**

```http
GET /api/users?limit=20
```

Response:

```json
{
  "items": [...],
  "hasMore": true,
  "nextCursor": "550e8400-e29b-41d4-a716-446655440000"
}
```

**Next page:**

```http
GET /api/users?limit=20&after=550e8400-e29b-41d4-a716-446655440000
```

---

## Performance Comparison

| Method | Rows scanned at page 1000 | Consistent with inserts |
|--------|--------------------------|------------------------|
| OFFSET | 20,000 | No — rows can appear twice or disappear |
| Cursor | 20 | Yes — cursor is stable |

At 1 million rows, `OFFSET 999980 LIMIT 20` scans 1,000,000 rows.
Cursor `WHERE id > :cursor LIMIT 20` scans 20 rows with an index seek. **~50,000x faster.**

---

## Limitations

- **Non-sequential cursors**: The cursor must be on an indexed, monotonically increasing column (UUID v7 or auto-increment IDs work well; UUID v4 has random ordering which is fine for UUIDs stored as text because PostgreSQL UUID comparison is deterministic).
- **Jump to page N**: Not possible. Cursor pagination doesn't support "go to page 47". If you need this, use Elasticsearch or a keyset with a time-based cursor.
- **Reverse pagination**: Add a `before` cursor parameter and flip `ORDER BY id DESC`.

---

## When to use Offset vs Cursor

| Use offset when... | Use cursor when... |
|--------------------|-------------------|
| You need "go to page N" | You need infinite scroll or "load more" |
| Data is read-only / infrequently updated | Data changes frequently |
| Small datasets (< 10,000 rows) | Large datasets or high-traffic endpoints |
| Admin backends | Consumer-facing APIs |
