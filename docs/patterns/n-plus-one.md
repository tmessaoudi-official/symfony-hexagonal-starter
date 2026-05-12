# Pattern: N+1 Query Problem

## The Problem

You have 100 users and want to show each user's most recent order.

**Naive code:**

```php
$users = $userRepository->findAll(); // 1 query

foreach ($users as $user) {
    $order = $orderRepository->findLatestByUser($user->id()); // 1 query per user
    echo $user->name() . ': ' . $order->total();
}
```

**Result**: 1 + 100 = 101 database queries. For 1000 users: 1001 queries.
At ~1ms per query (optimistic): 101ms minimum just for DB round-trips.

## Why It Happens

The ORM hides individual queries behind method calls. Each `findLatestByUser` call is a `SELECT` with a `WHERE user_id = ?`. The loop makes this invisible until you enable query logging.

**How to detect it**: Enable Symfony Profiler in dev and look at the "Doctrine" tab. If you see dozens of near-identical queries, you have an N+1 problem.

---

## Solution A: DQL with JOIN FETCH

DQL (Doctrine Query Language) lets you `JOIN FETCH` associations in one query.
Use this when the association is mapped in your Doctrine entity.

```php
// In your Doctrine repository:
public function findAllWithLatestOrders(): array
{
    return $this->em->createQuery(
        'SELECT u, o
         FROM App\Infrastructure\Persistence\Doctrine\Entity\UserEntity u
         LEFT JOIN u.orders o
         WITH o.id = (
             SELECT MAX(o2.id)
             FROM App\Infrastructure\Persistence\Doctrine\Entity\OrderEntity o2
             WHERE o2.userId = u.id
         )'
    )->getResult();
}
```

**Result**: 1 query. Doctrine hydrates both entities from the single result set.

**When to use**: When the association is mapped in Doctrine AND you want full entity hydration (e.g. you'll call methods on the order entity afterwards).

**Caveat**: JOIN FETCH with collections returns duplicate rows that Doctrine deduplicates. For large collections, consider using `DISTINCT` or a `QueryBuilder` `setFirstResult`/`setMaxResults` with a subquery.

---

## Solution B: QueryBuilder with explicit JOIN

When you control which columns to hydrate (read models, DTOs), avoid full entity hydration:

```php
// In your Doctrine repository:
public function findUserSummaries(): array
{
    return $this->em->createQueryBuilder()
        ->select(
            'u.id',
            'u.email',
            'u.name',
            'MAX(o.createdAt) AS lastOrderAt',
            'COUNT(o.id) AS totalOrders'
        )
        ->from(UserEntity::class, 'u')
        ->leftJoin(OrderEntity::class, 'o', 'WITH', 'o.userId = u.id')
        ->groupBy('u.id, u.email, u.name')
        ->orderBy('u.name', 'ASC')
        ->getQuery()
        ->getArrayResult(); // Returns plain arrays — much faster than entity hydration
}
```

**Result**: 1 query. Returns `[['id' => ..., 'email' => ..., 'lastOrderAt' => ..., 'totalOrders' => ...], ...]`.

**When to use**: Query-side (read models). You're building a DTO or an API response — you don't need to call methods on the entity.

---

## Solution C: Separated Hydration (Two Queries)

For complex joins that produce cartesian explosions (many-to-many), sometimes two targeted queries outperform one JOIN:

```php
// Step 1: Fetch all users (1 query)
$users = $this->em->createQueryBuilder()
    ->select('u')
    ->from(UserEntity::class, 'u')
    ->getQuery()
    ->getResult();

$userIds = array_map(fn(UserEntity $u) => $u->getId(), $users);

// Step 2: Fetch latest order per user in ONE query using IN clause
$latestOrders = $this->em->createQueryBuilder()
    ->select('o')
    ->from(OrderEntity::class, 'o')
    ->where('o.userId IN (:ids)')
    ->andWhere('o.id = (SELECT MAX(o2.id) FROM OrderEntity o2 WHERE o2.userId = o.userId)')
    ->setParameter('ids', $userIds)
    ->getQuery()
    ->getResult();

// Index by userId for O(1) lookup in the merge loop
$orderByUserId = [];
foreach ($latestOrders as $order) {
    $orderByUserId[$order->getUserId()] = $order;
}

// Step 3: Merge in PHP (O(n), no DB round-trip)
foreach ($users as $user) {
    $order = $orderByUserId[$user->getId()] ?? null;
    // ...
}
```

**Result**: 2 queries, regardless of how many users there are.

**When to use**: When the JOIN produces a cartesian explosion (many tags, many orders) that makes the single-query result too large to hydrate. Also useful when the two entities come from different database connections or services.

---

## Measuring the Fix

```php
// Symfony Profiler (dev only):
// Before: 101 queries, 45ms
// After (JOIN FETCH): 1 query, 3ms

// In tests, use Doctrine's query counter:
$queryCount = count($this->em->getConnection()->getLogs());
```

**Rule of thumb**: if the query count scales with the result count, you have an N+1.
