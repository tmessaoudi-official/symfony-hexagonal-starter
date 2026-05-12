<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\StateProcessor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Application\Command\CreateUser\CreateUserCommand;
use App\Application\DTO\Input\CreateUserInput;
use App\Application\DTO\Output\UserOutput;
use App\Domain\Exception\UserAlreadyExistsException;
use App\Domain\Model\Email;
use App\Domain\Repository\UserRepository;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * CreateUserProcessor — the write adapter for POST /api/users.
 *
 * Responsibilities:
 * 1. Receive the validated CreateUserInput DTO from API Platform.
 * 2. Build and dispatch a CreateUserCommand via the command bus.
 * 3. Return a UserOutput DTO (API Platform serialises it as the response body).
 *
 * WHY not dispatch directly from the processor?
 * - The processor is an Infrastructure adapter. Commands belong to Application.
 * - The bus decouples the HTTP adapter from the handler — handlers can be moved
 *   to async workers without touching this class.
 *
 * WHY catch UserAlreadyExistsException here?
 * - HTTP status mapping is an Infrastructure concern.
 * - The domain knows nothing about HTTP — it throws a domain exception.
 * - This adapter catches it and converts to the appropriate HTTP error.
 *
 * @implements ProcessorInterface<CreateUserInput, UserOutput>
 */
final class CreateUserProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly MessageBusInterface $commandBus,
        private readonly UserRepository $users,
    ) {}

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): UserOutput
    {
        assert($data instanceof CreateUserInput);

        try {
            $this->commandBus->dispatch(new CreateUserCommand(
                email: $data->email,
                name: $data->name,
            ));
        } catch (UserAlreadyExistsException $e) {
            throw new ConflictHttpException($e->getMessage(), $e);
        }

        // After a successful create, fetch the user to return it in the response.
        // The command handler generates the ID internally — we look up by email.
        $user = $this->users->findByEmail(Email::fromString($data->email));

        if ($user === null) {
            // This should never happen unless there is a race condition between save and fetch.
            throw new \RuntimeException('User was saved but could not be retrieved.');
        }

        return new UserOutput(
            id: $user->id()->value(),
            email: $user->email()->value(),
            name: $user->name()->value(),
            status: $user->status()->value,
            createdAt: $user->createdAt(),
            updatedAt: $user->updatedAt(),
        );
    }
}
