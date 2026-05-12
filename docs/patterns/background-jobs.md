# Pattern: Background Jobs with Symfony Messenger

## The Problem

You need to send a welcome email to 10,000 new users imported via CSV.

**Naive approach:**

```php
foreach ($users as $user) {
    $this->mailer->sendWelcome($user->email, $user->name); // synchronous SMTP call
}
```

**What happens:**
- Each SMTP call takes ~50-200ms.
- 10,000 users × 150ms = 25 minutes of HTTP request time.
- The request times out (default PHP `max_execution_time` = 30s).
- Even if you extend the timeout: the browser disconnects, the server holds the connection, memory leaks accumulate.

---

## Solution: Symfony Messenger Async Dispatch

### Step 1: The event is already there

`UserCreated` domain event is already dispatched in `CreateUserCommandHandler`. The `SendWelcomeEmailOnUserCreated` handler processes it.

### Step 2: Configure async routing

In `config/packages/messenger.yaml`:

```yaml
framework:
    messenger:
        routing:
            'App\Domain\Event\UserCreated': async
```

Now `UserCreated` is dispatched to RabbitMQ. The HTTP response returns immediately. The worker (running as a separate process) picks up the message and calls `mailer->sendWelcome()`.

### Step 3: The worker

```bash
# Start the worker (already in docker-compose.yml as a 'worker' service)
bin/console messenger:consume async --time-limit=3600 --memory-limit=128M
```

The `--time-limit=3600` causes the worker to restart every hour. Combined with `restart: unless-stopped` in Docker Compose, this prevents memory leaks from accumulating indefinitely.

---

## Retry Strategy

When the email server is temporarily down, the message should be retried — not silently dropped.

```yaml
# config/packages/messenger.yaml
transports:
    async:
        dsn: '%env(MESSENGER_TRANSPORT_DSN)%'
        retry_strategy:
            max_retries: 3
            delay: 1000      # 1 second initial delay
            multiplier: 2    # exponential: 1s → 2s → 4s
            max_delay: 0     # no hard cap on delay
```

Exponential backoff prevents thundering herd: if the email server comes back after 5 minutes, all 10,000 messages don't hammer it simultaneously.

---

## Dead Letter Queue (DLQ)

After 3 retries, failed messages go to the `failed` transport (see `messenger.yaml`).

**Inspecting failed messages:**

```bash
# List failed messages
bin/console messenger:failed:show

# Retry a specific failed message
bin/console messenger:failed:retry 123

# Retry all failed messages
bin/console messenger:failed:retry --all

# Remove a failed message
bin/console messenger:failed:remove 123
```

**Monitoring the DLQ**: Set up an alert when the DLQ queue length exceeds a threshold. A growing DLQ indicates a systemic problem (email server down, bug in the handler).

---

## Idempotency

The worker processes each message AT LEAST ONCE (RabbitMQ guarantee). Network failures can cause duplicate delivery. Your handlers must be idempotent.

**For welcome emails**: Check whether the email was already sent before sending:

```php
public function __invoke(UserCreated $event): void
{
    if ($this->emailLog->wasWelcomeSentTo($event->email->value())) {
        $this->logger->info('Welcome email already sent, skipping', [
            'userId' => $event->userId->value(),
        ]);
        return; // Idempotent — safe to skip
    }

    $this->mailer->sendWelcome(
        to: $event->email->value(),
        name: $event->name->value(),
    );

    $this->emailLog->recordWelcomeSent($event->email->value());
}
```

**Alternative**: Use Symfony Messenger's built-in duplicate detection with a unique message ID stamp.

---

## Monitoring

Key metrics to track for your Messenger workers:

| Metric | Alert threshold |
|--------|----------------|
| Queue depth (pending messages) | > 10,000 |
| DLQ depth (failed messages) | > 0 |
| Worker uptime | Restart within 5 minutes of crash |
| Processing latency (enqueue → process time) | > 30 seconds |

**RabbitMQ Management UI**: Available at `http://localhost:15672` (guest/guest in dev).

**Prometheus + Grafana**: Symfony Messenger exposes metrics via `prometheus/client_php` if you add the instrumentation bundle.

---

## Long-Running Jobs

For very long operations (bulk email to 1M users), dispatch a single "BulkEmailJob" message containing the user criteria (not the user list). The worker runs the query and sends emails in batches:

```php
#[AsMessageHandler]
final class BulkEmailJobHandler
{
    public function __invoke(BulkEmailJob $job): void
    {
        $cursor = null;

        do {
            $users = $this->users->findPage(limit: 500, cursor: $cursor);

            foreach ($users as $user) {
                $this->mailer->sendWelcome($user->email()->value(), $user->name()->value());
            }

            $cursor = !empty($users) ? end($users)->id() : null;
            // Allow GC to collect; prevents memory leak in long-running workers
            gc_collect_cycles();
        } while ($cursor !== null);
    }
}
```

This pattern keeps the message size small (just the query criteria) and uses cursor pagination (see `docs/patterns/cursor-pagination.md`) to avoid loading all users into memory at once.
