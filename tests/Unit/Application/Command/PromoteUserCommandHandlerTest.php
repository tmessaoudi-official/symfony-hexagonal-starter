<?php

declare(strict_types=1);

use App\Application\Command\PromoteUser\PromoteUserCommand;
use App\Application\Command\PromoteUser\PromoteUserCommandHandler;
use App\Domain\Event\UserPromoted;
use App\Domain\Model\Email;
use App\Domain\Model\User;
use App\Domain\Model\UserId;
use App\Domain\Model\UserName;
use App\Domain\Model\UserRole;
use App\Domain\Model\UserStatus;
use App\Domain\Service\UserPromotionPolicy;
use App\Tests\Support\InMemoryUserRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

uses(TestCase::class);

describe('PromoteUserCommandHandler (Unit)', function (): void {
    beforeEach(function (): void {
        // Real policy + in-memory repository: only the event bus is a test double.
        $this->repo = new InMemoryUserRepository();
        $this->dispatched = [];
        $this->bus = $this->createMock(MessageBusInterface::class);
        $this->bus->method('dispatch')->willReturnCallback(function (object $event): Envelope {
            $this->dispatched[] = $event;

            return new Envelope($event);
        });
        $this->handler = new PromoteUserCommandHandler($this->repo, new UserPromotionPolicy(), $this->bus);

        $this->seed = function (UserRole $role, int $ageDays): User {
            $createdAt = new DateTimeImmutable("-{$ageDays} days");
            $user = User::reconstitute(
                id: UserId::generate(),
                email: Email::fromString(uniqid('promote', true).'@example.com'),
                name: UserName::fromString('Candidate'),
                status: UserStatus::Active,
                role: $role,
                createdAt: $createdAt,
                updatedAt: $createdAt,
            );
            $this->repo->save($user);

            return $user;
        };
    });

    it('promotes an eligible user, saves it and publishes UserPromoted', function (): void {
        $user = ($this->seed)(UserRole::Regular, 60);

        ($this->handler)(new PromoteUserCommand(userId: $user->id()->value(), targetRole: 'moderator'));

        $event = $this->dispatched[0] ?? null;
        expect($this->repo->getById($user->id())->role())->toBe(UserRole::Moderator)
            ->and($this->dispatched)->toHaveCount(1)
            ->and($event)->toBeInstanceOf(UserPromoted::class);
        expect($event->userId->equals($user->id()))->toBeTrue()
            ->and($event->previousRole)->toBe(UserRole::Regular)
            ->and($event->newRole)->toBe(UserRole::Moderator);
    });

    it('rejects an ineligible promotion with the policy reason and publishes nothing', function (): void {
        $user = ($this->seed)(UserRole::Regular, 5);

        expect(fn () => ($this->handler)(new PromoteUserCommand(userId: $user->id()->value(), targetRole: 'moderator')))
            ->toThrow(DomainException::class, '30');

        expect($this->repo->getById($user->id())->role())->toBe(UserRole::Regular)
            ->and($this->dispatched)->toBe([]);
    });

    it('rejects an unknown target role', function (): void {
        $user = ($this->seed)(UserRole::Regular, 60);

        ($this->handler)(new PromoteUserCommand(userId: $user->id()->value(), targetRole: 'superuser'));
    })->throws(ValueError::class);
});
