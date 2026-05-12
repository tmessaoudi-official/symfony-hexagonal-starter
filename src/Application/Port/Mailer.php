<?php

declare(strict_types=1);

namespace App\Application\Port;

/**
 * Mailer port — outbound interface for sending email.
 *
 * WHY an interface in Application, not Infrastructure?
 * - The Application layer needs to send email; it doesn't care HOW.
 * - This interface defines the contract. Infrastructure provides the adapter
 *   (e.g. SymfonyMailerAdapter using Symfony\Component\Mailer).
 * - Swapping from Symfony Mailer to SendGrid requires only a new adapter class.
 * - Tests can inject a NullMailer or a SpyMailer without touching application logic.
 */
interface Mailer
{
    public function sendWelcome(string $to, string $name): void;
}
