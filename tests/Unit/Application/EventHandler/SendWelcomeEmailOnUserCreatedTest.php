<?php

declare(strict_types=1);

use App\Application\EventHandler\SendWelcomeEmailOnUserCreated;
use App\Application\Port\Mailer;
use App\Domain\Event\UserCreated;
use App\Domain\Model\Email;
use App\Domain\Model\UserId;
use App\Domain\Model\UserName;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\NullLogger;

uses(\PHPUnit\Framework\TestCase::class);

/**
 * @var Mailer&MockObject $mailer
 * @var SendWelcomeEmailOnUserCreated $handler
 */

describe('SendWelcomeEmailOnUserCreated (Unit)', function (): void {
    beforeEach(function (): void {
        $this->mailer  = $this->createMock(Mailer::class);
        $this->handler = new SendWelcomeEmailOnUserCreated($this->mailer, new NullLogger());
    });

    it('calls the mailer port with the correct email address and name', function (): void {
        $event = new UserCreated(
            userId: UserId::generate(),
            email: Email::fromString('welcome@example.com'),
            name: UserName::fromString('Welcome User'),
        );

        $this->mailer
            ->expects($this->once())
            ->method('sendWelcome')
            ->with('welcome@example.com', 'Welcome User');

        ($this->handler)($event);
    });

    it('passes the lowercased email to the mailer', function (): void {
        // Email VO normalises to lowercase — verify the string passed to mailer is already normalised.
        $event = new UserCreated(
            userId: UserId::generate(),
            email: Email::fromString('UPPER@Example.COM'),
            name: UserName::fromString('Upper User'),
        );

        $this->mailer
            ->expects($this->once())
            ->method('sendWelcome')
            ->with('upper@example.com', 'Upper User');

        ($this->handler)($event);
    });

    it('sends exactly one email per UserCreated event', function (): void {
        $event = new UserCreated(
            userId: UserId::generate(),
            email: Email::fromString('once@example.com'),
            name: UserName::fromString('Once Only'),
        );

        $this->mailer->expects($this->exactly(1))->method('sendWelcome');

        ($this->handler)($event);
    });
});
