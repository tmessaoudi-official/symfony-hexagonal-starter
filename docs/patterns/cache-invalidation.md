# Pattern: Cache Invalidation

> "There are only two hard things in Computer Science: cache invalidation and naming things."
> — Phil Karlton

## The Problem

You cache a user's profile for 1 hour:

```php
$user = $this->cache->get('user_' . $id, fn() => $this->repo->findById($id));
```

A request updates the user's email. The cache is now stale — for up to 1 hour, API responses return the old email.

**Approaches and their tradeoffs:**

| Approach | Stale window | Complexity |
|----------|-------------|------------|
| Time-based TTL only | Up to TTL | Simple |
| On-write invalidation | ~0ms | Medium |
| Tag-based invalidation | ~0ms | Medium |
| Cache stampede protection | N/A (latency) | High |

---

## Solution: Tag-Based Cache Invalidation

Symfony Cache supports tagging cached items. When a user is updated, invalidate all items tagged with that user's ID.

**Step 1 — Enable tag support in cache config:**

```yaml
# config/packages/cache.yaml (already configured in this starter)
framework:
    cache:
        pools:
            cache.users:
                adapter: cache.adapter.redis
                tags: true
```

**Step 2 — Cache with tags:**

```php
// In a caching decorator that wraps DoctrineUserRepository
final class CachingUserRepository implements UserRepository
{
    public function __construct(
        private readonly UserRepository $inner,
        private readonly TagAwareCacheInterface $cache,
    ) {}

    public function getById(UserId $id): User
    {
        $cacheKey = 'user_' . $id->value();

        return $this->cache->get(
            key: $cacheKey,
            callback: fn() => $this->inner->getById($id),
            beta: 1.0,  // early expiry factor (see "Cache Stampede" below)
            metadata: [
                ItemInterface::METADATA_TAGS => ['user', 'user_' . $id->value()],
                ItemInterface::METADATA_EXPIRY => time() + 3600,
            ],
        );
    }

    public function save(User $user): void
    {
        $this->inner->save($user);

        // Invalidate the cached entry for this specific user
        $this->cache->invalidateTags(['user_' . $user->id()->value()]);
    }
}
```

**Tags used:**
- `user` — tags ALL user cache entries (useful for bulk invalidation, e.g. schema migration).
- `user_{id}` — tags one specific user's entry.

---

## Cache-Aside Pattern

The cache layer is transparent to the application. The application always goes through the `UserRepository` interface. Whether it hits Redis or the DB is decided by which implementation is wired.

```
Application → UserRepository interface
                     ↓
              CachingUserRepository (checks Redis)
                     ↓ (on miss)
              DoctrineUserRepository (queries DB)
                     ↑ (stores result in Redis)
```

**Key rule**: The cache is always invalidated AFTER the write to the primary store. Never before.

---

## Cache Stampede (Thundering Herd)

**The problem**: 100 requests arrive simultaneously for a key that just expired.
All 100 miss the cache, all 100 query the DB simultaneously — 100x DB load spike.

**Solution**: Probabilistic Early Expiry (Symfony `$beta` parameter)

```php
// beta > 0 enables "Probabilistic Early Recomputation"
// Symfony randomly recomputes the cache BEFORE it expires.
// Higher beta = recompute earlier = more cache misses but no stampede.
// beta = 1.0 is the recommended default.
$this->cache->get(key: $key, callback: $recompute, beta: 1.0);
```

How it works: when the remaining TTL is small, the probability of early recomputation increases. One request recomputes it slightly early; subsequent requests still get the cached value. The stampede is prevented because the cache is refreshed before expiry.

**Alternative**: Use Symfony Lock to allow only one process to recompute:

```php
$lock = $this->lockFactory->createLock('cache_user_' . $id->value());

if (!$lock->acquire()) {
    // Another process is recomputing — wait a moment and retry from cache
    usleep(50_000); // 50ms
    return $this->getById($id); // recursive call — will likely hit cache
}

try {
    $user = $this->inner->getById($id);
    $this->cache->set('user_' . $id->value(), $user, 3600);
    return $user;
} finally {
    $lock->release();
}
```

---

## Registering the Caching Decorator

Wire the caching decorator in `services.yaml` so the Application layer gets it:

```yaml
# services.yaml
App\Domain\Repository\UserRepository:
    class: App\Infrastructure\Cache\CachingUserRepository
    arguments:
        $inner: '@App\Infrastructure\Persistence\Doctrine\Repository\DoctrineUserRepository'
        $cache: '@cache.users'
```

The Application layer depends on `UserRepository` (the interface). DI injects `CachingUserRepository`, which internally calls `DoctrineUserRepository`. No application code changes.

---

## When NOT to Cache

| Situation | Why caching hurts |
|-----------|------------------|
| Highly personalised data | Cache key explosion; memory inefficiency |
| Write-heavy entities | Invalidation overhead exceeds cache savings |
| Strong consistency required | Even 50ms stale data is unacceptable |
| Small datasets (< 100 rows) | DB is already fast; cache is overhead |

**Measure before caching**: Profile with the Symfony Profiler first. Caching a query that takes 2ms saves almost nothing; adding cache complexity to a 2ms query is rarely worth it.
