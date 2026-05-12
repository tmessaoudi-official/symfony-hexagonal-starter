<?php

declare(strict_types=1);

use App\Application\Command\CreateUser\CreateUserCommand;
use App\Application\Command\CreateUser\CreateUserCommandHandler;
use App\Domain\Model\Email;
use App\Domain\Repository\UserRepository;
use Behat\Behat\Context\Context;
use Behat\Gherkin\Node\PyStringNode;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * Feature context for Behat API scenarios.
 *
 * This is intentionally minimal — it delegates HTTP calls to Behatch contexts
 * (RestContext, JsonContext) and provides domain-specific steps only.
 *
 * WHY not use FriendsOfBehat\SymfonyExtension steps directly?
 * - We do use the extension (see behat.yml). This class adds custom Gherkin steps
 *   that require domain knowledge (e.g. "Given there is a user with email X").
 * - API-level HTTP steps come from friends-of-behat/mink-extension.
 */
final class FeatureContext implements Context
{
    /** @var array<string, string> Maps email → userId for within-scenario reference */
    private array $userIdByEmail = [];

    public function __construct(
        private readonly KernelInterface $kernel,
    ) {}

    /**
     * @Given there is a user with email :email and name :name
     */
    public function thereIsAUserWithEmailAndName(string $email, string $name): void
    {
        $container = $this->kernel->getContainer();
        /** @var CreateUserCommandHandler $handler */
        $handler = $container->get(CreateUserCommandHandler::class);
        $handler(new CreateUserCommand(email: $email, name: $name));

        // Capture the generated ID for use in subsequent steps.
        /** @var UserRepository $repo */
        $repo = $container->get(UserRepository::class);
        $user = $repo->findByEmail(Email::fromString($email));
        if ($user !== null) {
            $this->userIdByEmail[$email] = $user->id()->value();
        }
    }

    /**
     * @Given there are :count users in the system
     */
    public function thereAreUsersInTheSystem(int $count): void
    {
        $container = $this->kernel->getContainer();
        /** @var CreateUserCommandHandler $handler */
        $handler = $container->get(CreateUserCommandHandler::class);

        for ($i = 1; $i <= $count; $i++) {
            $handler(new CreateUserCommand(
                email: "user{$i}@example.com",
                name: "User {$i}",
            ));
        }
    }

    /**
     * Replace placeholder {email::id} tokens in a URL with the actual user ID.
     * Example: /api/users/{findme@example.com::id} → /api/users/actual-uuid
     */
    public function resolveUrl(string $url): string
    {
        return preg_replace_callback(
            '/\{([^}]+)::id\}/',
            fn (array $m): string => $this->userIdByEmail[$m[1]] ?? $m[0],
            $url,
        ) ?? $url;
    }
}
