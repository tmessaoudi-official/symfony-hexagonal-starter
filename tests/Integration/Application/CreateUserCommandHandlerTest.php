<?php

declare(strict_types=1);

use App\Application\Command\CreateUser\CreateUserCommand;
use App\Application\Command\CreateUser\CreateUserCommandHandler;
use App\Domain\Exception\UserAlreadyExistsException;
use App\Domain\Model\Email;
use App\Domain\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Test\TestContainer;

/*
 * Integration test for CreateUserCommandHandler.
 *
 * Uses KernelTestCase (wired in tests/Pest.php) with DAMA DoctrineTestBundle:
 * - The test database is migrated before the suite runs (see phpunit.xml.dist extensions).
 * - Each test runs inside a transaction that is rolled back at teardown.
 * - Tests therefore start with a clean database every time.
 */
describe('CreateUserCommandHandler (Integration)', function (): void {
    beforeEach(function (): void {
        // getContainer() is typed Container but returns the test container, which exposes private services.
        /** @var TestContainer $container */
        $container = $this->getContainer();
        $this->handler = $container->get(CreateUserCommandHandler::class);
        $this->repo = $container->get(UserRepository::class);
    });

    it('creates a user and persists it to the database', function (): void {
        $command = new CreateUserCommand(email: 'alice@example.com', name: 'Alice Smith');
        ($this->handler)($command);

        $email = Email::fromString('alice@example.com');
        $user = $this->repo->findByEmail($email);

        expect($user)->not->toBeNull()
            ->and($user->email()->value())->toBe('alice@example.com')
            ->and($user->name()->value())->toBe('Alice Smith');
    });

    it('throws UserAlreadyExistsException when email is already registered', function (): void {
        $command = new CreateUserCommand(email: 'duplicate@example.com', name: 'First');
        ($this->handler)($command);

        // Second call with the same email should throw.
        ($this->handler)(new CreateUserCommand(email: 'duplicate@example.com', name: 'Second'));
    })->throws(UserAlreadyExistsException::class);
});
