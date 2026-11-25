<?php

declare(strict_types=1);

use App\Application\Command\PromoteUser\PromoteUserCommand;
use App\Domain\Model\Email;
use App\Domain\Model\User;
use App\Domain\Model\UserId;
use App\Domain\Model\UserName;
use App\Domain\Model\UserRole;
use App\Domain\Model\UserStatus;
use App\Domain\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Test\TestContainer;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;

/*
 * Integration test for PromoteUserCommandHandler, dispatched through the real command bus
 * (with its doctrine_transaction middleware), so the container wiring is under test too:
 * the UserPromoted event the handler publishes must reach the EVENT bus, which accepts
 * events without handlers. The command bus does not, and would fail the promotion.
 */
describe('PromoteUserCommandHandler (Integration)', function (): void {
    beforeEach(function (): void {
        // getContainer() is typed Container but returns the test container, which exposes private services.
        /** @var TestContainer $container */
        $container = $this->getContainer();
        $this->commandBus = $container->get('command.bus');
        $this->repo = $container->get(UserRepository::class);

        $this->seed = function (UserRole $role, int $ageDays): UserId {
            $createdAt = new DateTimeImmutable("-{$ageDays} days");
            $id = UserId::generate();
            $this->repo->save(User::reconstitute(
                id: $id,
                email: Email::fromString(uniqid('promote', true).'@example.com'),
                name: UserName::fromString('Promotion Candidate'),
                status: UserStatus::Active,
                role: $role,
                createdAt: $createdAt,
                updatedAt: $createdAt,
            ));

            return $id;
        };
    });

    it('promotes an eligible user and persists the new role', function (): void {
        /** @var MessageBusInterface $bus */
        $bus = $this->commandBus;
        $id = ($this->seed)(UserRole::Regular, 60);

        $bus->dispatch(new PromoteUserCommand(userId: $id->value(), targetRole: UserRole::Moderator->value));

        expect($this->repo->getById($id)->role())->toBe(UserRole::Moderator);
    });

    it('refuses an ineligible promotion and keeps the role', function (): void {
        /** @var MessageBusInterface $bus */
        $bus = $this->commandBus;
        $id = ($this->seed)(UserRole::Regular, 5); // under the 30-day minimum

        expect(fn () => $bus->dispatch(new PromoteUserCommand(userId: $id->value(), targetRole: UserRole::Moderator->value)))
            ->toThrow(HandlerFailedException::class, '30');

        expect($this->repo->getById($id)->role())->toBe(UserRole::Regular);
    });
});
