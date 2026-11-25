<?php

declare(strict_types=1);

namespace App\Application\EventHandler;

use App\Application\Port\Mailer;
use App\Domain\Event\UserCreated;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Reacts to UserCreated domain events and queues a welcome email.
 *
 * WHY this lives in Application (not Infrastructure)?
 * - This handler orchestrates application-level behaviour: "when a user is created,
 *   send a welcome email". It knows the WHAT but not the HOW.
 * - The HOW is the Mailer port (Application\Port\Mailer) — implemented by Infrastructure.
 *
 * WHY #[AsMessageHandler]?
 * - This marks the class for Symfony Messenger auto-wiring.
 * - The event bus (in CreateUserCommandHandler) dispatches UserCreated.
 * - Messenger routes it here asynchronously (see config/packages/messenger.yaml).
 */
#[AsMessageHandler(bus: 'event.bus')]
final readonly class SendWelcomeEmailOnUserCreated
{
    public function __construct(
        private Mailer $mailer,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(UserCreated $event): void
    {
        $this->logger->info('Sending welcome email', [
            'userId' => $event->userId->value(),
            'email' => $event->email->value(),
        ]);

        $this->mailer->sendWelcome(
            to: $event->email->value(),
            name: $event->name->value(),
        );
    }
}
