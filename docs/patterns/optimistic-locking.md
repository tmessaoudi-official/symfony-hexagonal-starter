# Pattern: Optimistic Locking

## The Problem

Two API requests arrive simultaneously for the same resource:

```
Request A: GET /api/users/123       → {name: "Alice", version: 1}
Request B: GET /api/users/123       → {name: "Alice", version: 1}

Request A: PATCH /api/users/123     {name: "Alice Smith"}   ← saves, version becomes 2
Request B: PATCH /api/users/123     {name: "Alice Jones"}   ← also saves, overwrites A silently
```

Request B is now "last write wins". Alice's update from request A is silently lost.

This is a **lost update problem** (also called the ABA problem in concurrency). It happens in any system where reads and writes are separate requests.

---

## Solution A: Doctrine @Version Column (Optimistic Locking)

Add a version column to the Doctrine entity. Doctrine checks it on every UPDATE.

**Step 1 — Add version to the entity:**

```php
#[ORM\Entity]
#[ORM\Table(name: 'users')]
class UserEntity
{
    // ... other properties ...

    #[ORM\Version]
    #[ORM\Column(type: 'integer')]
    private int $version = 1;

    public function getVersion(): int
    {
        return $this->version;
    }
}
```

**Step 2 — Add migration:**

```sql
ALTER TABLE users ADD COLUMN version INTEGER NOT NULL DEFAULT 1;
```

**Step 3 — Include version in the API response:**

The `UserOutput` DTO gains a `version` field. Clients receive it in GET responses.

**Step 4 — Require version on writes (ETag approach):**

```php
// In the StateProcessor, before updating:
use Doctrine\ORM\OptimisticLockException;

try {
    $this->em->lock($entity, LockMode::OPTIMISTIC, $data->version);
    // ... apply changes ...
    $this->em->flush();
} catch (OptimisticLockException $e) {
    throw new ConflictHttpException(
        'This resource was modified by another request. Fetch the latest version and retry.',
        $e,
    );
}
```

Doctrine's `lock()` check: `UPDATE users SET ... WHERE id = ? AND version = ?`.
If `version` no longer matches (someone else updated it), the UPDATE affects 0 rows, and Doctrine throws `OptimisticLockException`.

---

## Solution B: HTTP ETag + If-Match

HTTP has a built-in mechanism for this: ETags.

**GET response:**

```http
HTTP/1.1 200 OK
ETag: "user-123-v1"
Content-Type: application/json

{"id": "123", "name": "Alice", "version": 1}
```

**PATCH request from client:**

```http
PATCH /api/users/123 HTTP/1.1
If-Match: "user-123-v1"
Content-Type: application/json

{"name": "Alice Smith"}
```

**Server-side check:**

```php
// In the StateProcessor:
$ifMatch = $request->headers->get('If-Match');

if ($ifMatch !== null && $ifMatch !== '"user-' . $user->id()->value() . '-v' . $entity->getVersion() . '"') {
    throw new PreconditionFailedHttpException(
        'ETag mismatch. The resource has been modified. Please fetch the latest version.',
    );
}
```

If the client omits `If-Match`, return `428 Precondition Required`.

**The HTTP response codes:**
- `412 Precondition Failed` — ETag doesn't match (conflict).
- `409 Conflict` — Optimistic lock failed (Doctrine approach).
- `428 Precondition Required` — Client must send `If-Match`.

---

## Choosing Between the Two Approaches

| | Doctrine @Version | HTTP ETag |
|---|---|---|
| Where the check happens | Database level | Application/HTTP level |
| Client awareness needed? | Yes (must send version field) | Yes (must send If-Match header) |
| Works with async writes (queues)? | Not directly | Not directly |
| Standard HTTP mechanism? | No | Yes (RFC 7232) |
| Caching headers compatible? | N/A | Yes (CDN/browser cache validation) |

**Recommendation**: Use HTTP ETag for public APIs (REST semantics, CDN compatibility). Use Doctrine `@Version` for internal services or when ETag header management is inconvenient.

---

## Testing Optimistic Locking

```php
it('returns 409 when two requests conflict', function (): void {
    $user = createUser('alice@example.com');

    // Simulate two clients reading the same version
    $versionA = $user->getVersion(); // = 1

    // First update succeeds
    $this->patch("/api/users/{$user->getId()}", ['name' => 'Alice Smith', 'version' => $versionA]);
    expect($this->response->getStatusCode())->toBe(200);

    // Second update with the stale version fails
    $this->patch("/api/users/{$user->getId()}", ['name' => 'Alice Jones', 'version' => $versionA]);
    expect($this->response->getStatusCode())->toBe(409);
});
```
