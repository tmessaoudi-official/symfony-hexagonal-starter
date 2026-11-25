<?php

declare(strict_types=1);

namespace App\Tests\Behat;

use App\Application\Command\CreateUser\CreateUserCommand;
use App\Domain\Model\Email;
use App\Domain\Model\User;
use App\Domain\Repository\UserRepository;
use Behat\Behat\Context\Context;
use Behat\Step\Given;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Domain-level Given steps: they put the system in a known state through the application's
 * own command bus (the same path as production), never by inserting rows by hand.
 */
final class UserContext implements Context
{
    /** @var array<string, string> email => user id, for {email::id} placeholders in URLs */
    private array $userIdByEmail = [];

    public function __construct(
        private readonly MessageBusInterface $commandBus,
        private readonly UserRepository $users,
    ) {
    }

    #[Given('there is a user with email :email and name :name')]
    public function thereIsAUserWithEmailAndName(string $email, string $name): void
    {
        $this->commandBus->dispatch(new CreateUserCommand(email: $email, name: $name));

        $user = $this->users->findByEmail(Email::fromString($email));
        if (!$user instanceof User) {
            throw new \RuntimeException(sprintf('User "%s" was not created.', $email));
        }
        $this->userIdByEmail[$email] = $user->id()->value();
    }

    #[Given('there are :count users in the system')]
    public function thereAreUsersInTheSystem(int $count): void
    {
        for ($i = 1; $i <= $count; ++$i) {
            $this->thereIsAUserWithEmailAndName("user{$i}@example.com", "User {$i}");
        }
    }

    /**
     * Replaces {email::id} tokens with the id of the user created earlier in the scenario.
     * Example: /api/users/{findme@example.com::id} -> /api/users/<uuid>.
     */
    public function resolveUrl(string $url): string
    {
        return preg_replace_callback(
            '/\{([^}]+)::id\}/',
            fn (array $m): string => $this->userIdByEmail[$m[1]]
                ?? throw new \RuntimeException(sprintf('No user "%s" in this scenario.', $m[1])),
            $url,
        ) ?? $url;
    }
}
