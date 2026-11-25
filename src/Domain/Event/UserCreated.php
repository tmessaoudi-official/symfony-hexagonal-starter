<?php

declare(strict_types=1);

namespace App\Domain\Event;

use App\Domain\Model\Email;
use App\Domain\Model\UserId;
use App\Domain\Model\UserName;

/**
 * UserCreated Domain Event.
 *
 * WHY domain events?
 * - They decouple side-effects (send welcome email, update analytics, create audit log)
 *   from the core use case (create a user).
 * - The domain says WHAT happened. Infrastructure decides WHAT TO DO about it.
 * - Events are immutable records of facts — they never change after creation.
 *
 * WHY POPO (Plain Old PHP Object) and not a Symfony Message?
 * - Domain events live in the Domain layer — no framework dependencies allowed.
 * - The EventHandler in Application\EventHandler wraps this in a Messenger message.
 *   This keeps the domain pure and the messaging infrastructure swappable.
 */
final readonly class UserCreated
{
    public \DateTimeImmutable $occurredAt;

    public function __construct(
        public UserId $userId,
        public Email $email,
        public UserName $name,
    ) {
        $this->occurredAt = new \DateTimeImmutable();
    }
}
