<?php

declare(strict_types=1);

namespace App\Application\Command\CreateUser;

use App\Domain\Exception\UserAlreadyExistsException;
use App\Domain\Model\Email;
use App\Domain\Model\User;
use App\Domain\Model\UserId;
use App\Domain\Model\UserName;
use App\Domain\Repository\UserRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * CreateUserCommandHandler — the orchestrator for user creation.
 *
 * WHY does the handler return void?
 * - Commands change state. The result of the change is observable via a subsequent Query.
 * - Returning data from a CommandHandler mixes Command and Query concerns (violates CQRS).
 * - If the caller needs the new user's ID immediately, it should generate it before dispatch
 *   (note: CreateUserCommand does NOT include a pre-generated ID here — the handler generates one.
 *    An alternative pattern pre-generates the ID in the Command itself; choose based on your needs.)
 *
 * WHY inject MessageBusInterface for events?
 * - Domain events are recorded on the aggregate during creation.
 * - The handler pulls them and dispatches to the event bus — Infrastructure decides what to do.
 * - If Messenger is swapped for a different bus tomorrow, only this handler changes.
 */
#[AsMessageHandler(bus: 'command.bus')]
final class CreateUserCommandHandler
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly MessageBusInterface $eventBus,
    ) {}

    public function __invoke(CreateUserCommand $command): void
    {
        $email = Email::fromString($command->email);

        if ($this->users->existsByEmail($email)) {
            throw UserAlreadyExistsException::withEmail($email);
        }

        $user = User::create(
            id: UserId::generate(),
            email: $email,
            name: UserName::fromString($command->name),
        );

        $this->users->save($user);

        foreach ($user->pullDomainEvents() as $event) {
            $this->eventBus->dispatch($event);
        }
    }
}
