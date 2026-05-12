<?php

declare(strict_types=1);

namespace App\Infrastructure\Messaging;

use App\Application\Port\Mailer;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email as SymfonyEmail;

/**
 * SymfonyMailerAdapter — implements the Mailer port using Symfony Mailer.
 *
 * WHY this adapter pattern?
 * - The Application layer depends on App\Application\Port\Mailer (an interface it owns).
 * - This class adapts Symfony's MailerInterface to that contract.
 * - Replacing Symfony Mailer with SendGrid means writing a new adapter, not touching
 *   application logic.
 */
final class SymfonyMailerAdapter implements Mailer
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly LoggerInterface $logger,
        private readonly string $fromAddress,
        private readonly string $fromName,
    ) {}

    public function sendWelcome(string $to, string $name): void
    {
        $email = (new SymfonyEmail())
            ->from(sprintf('%s <%s>', $this->fromName, $this->fromAddress))
            ->to($to)
            ->subject(sprintf('Welcome, %s!', $name))
            ->html(sprintf(
                '<p>Hello %s,</p><p>Welcome aboard! Your account has been created.</p>',
                htmlspecialchars($name, ENT_QUOTES),
            ));

        $this->mailer->send($email);

        $this->logger->info('Welcome email sent', ['to' => $to]);
    }
}
