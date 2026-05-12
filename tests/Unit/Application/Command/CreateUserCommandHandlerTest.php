<?php

declare(strict_types=1);

use App\Application\Command\CreateUser\CreateUserCommand;
use App\Application\Command\CreateUser\CreateUserCommandHandler;
use App\Domain\Event\UserCreated;
use App\Domain\Exception\UserAlreadyExistsException;
use App\Domain\Model\Email;
use App\Domain\Model\User;
use App\Domain\Repository\UserRepository;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

uses(\PHPUnit\Framework\TestCase::class);

/**
 * @var UserRepository&MockObject $repo
 * @var MessageBusInterface&MockObject $bus
 * @var CreateUserCommandHandler $handler
 */

describe('CreateUserCommandHandler (Unit)', function (): void {
    beforeEach(function (): void {
        $this->repo    = $this->createMock(UserRepository::class);
        $this->bus     = $this->createMock(MessageBusInterface::class);
        $this->handler = new CreateUserCommandHandler($this->repo, $this->bus);
    });

    it('saves a new user to the repository', function (): void {
        $this->repo
            ->method('existsByEmail')
            ->willReturn(false);

        $this->repo
            ->expects($this->once())
            ->method('save')
            ->with($this->isInstanceOf(User::class));

        $this->bus
            ->method('dispatch')
            ->willReturn(new Envelope(new stdClass()));

        ($this->handler)(new CreateUserCommand(email: 'bob@example.com', name: 'Bob Builder'));
    });

    it('dispatches a UserCreated event to the event bus', function (): void {
        $this->repo
            ->method('existsByEmail')
            ->willReturn(false);
        $this->repo->method('save');

        $dispatchedEvent = null;
        $this->bus
            ->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(function (object $event) use (&$dispatchedEvent): Envelope {
                $dispatchedEvent = $event;

                return new Envelope($event);
            });

        ($this->handler)(new CreateUserCommand(email: 'carol@example.com', name: 'Carol King'));

        expect($dispatchedEvent)->toBeInstanceOf(UserCreated::class)
            ->and($dispatchedEvent->email->value())->toBe('carol@example.com')
            ->and($dispatchedEvent->name->value())->toBe('Carol King');
    });

    it('saves the user with the correct email and name', function (): void {
        $this->repo
            ->method('existsByEmail')
            ->willReturn(false);

        $savedUser = null;
        $this->repo
            ->expects($this->once())
            ->method('save')
            ->willReturnCallback(function (User $user) use (&$savedUser): void {
                $savedUser = $user;
            });

        $this->bus
            ->method('dispatch')
            ->willReturn(new Envelope(new stdClass()));

        ($this->handler)(new CreateUserCommand(email: 'dave@example.com', name: 'Dave Duncan'));

        expect($savedUser)->toBeInstanceOf(User::class)
            ->and($savedUser->email()->value())->toBe('dave@example.com')
            ->and($savedUser->name()->value())->toBe('Dave Duncan');
    });

    it('throws UserAlreadyExistsException when the email is already registered', function (): void {
        $this->repo
            ->method('existsByEmail')
            ->with($this->callback(fn (Email $e): bool => $e->value() === 'taken@example.com'))
            ->willReturn(true);

        $this->repo->expects($this->never())->method('save');
        $this->bus->expects($this->never())->method('dispatch');

        ($this->handler)(new CreateUserCommand(email: 'taken@example.com', name: 'Taken Name'));
    })->throws(UserAlreadyExistsException::class);
});
