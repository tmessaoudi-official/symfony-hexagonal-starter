<?php

declare(strict_types=1);

use App\Domain\Event\UserCreated;
use App\Domain\Model\Email;
use App\Domain\Model\User;
use App\Domain\Model\UserId;
use App\Domain\Model\UserName;
use App\Domain\Model\UserStatus;
use DateTimeImmutable;

describe('User Aggregate', function (): void {
    it('creates a user with active status and records a UserCreated event', function (): void {
        $id    = UserId::generate();
        $email = Email::fromString('alice@example.com');
        $name  = UserName::fromString('Alice Smith');

        $user = User::create($id, $email, $name);

        expect($user->id())->toBe($id)
            ->and($user->email())->toBe($email)
            ->and($user->name())->toBe($name)
            ->and($user->status())->toBe(UserStatus::Active);

        $events = $user->pullDomainEvents();
        expect($events)->toHaveCount(1)
            ->and($events[0])->toBeInstanceOf(UserCreated::class);
    });

    it('clears domain events after pulling them', function (): void {
        $user = User::create(UserId::generate(), Email::fromString('a@b.com'), UserName::fromString('AB'));
        $user->pullDomainEvents(); // first pull
        $events = $user->pullDomainEvents(); // second pull should be empty
        expect($events)->toBeEmpty();
    });

    it('does NOT record an event when reconstituted', function (): void {
        $user = User::reconstitute(
            id: UserId::generate(),
            email: Email::fromString('a@b.com'),
            name: UserName::fromString('AB'),
            status: UserStatus::Active,
            createdAt: new DateTimeImmutable(),
            updatedAt: new DateTimeImmutable(),
        );

        expect($user->pullDomainEvents())->toBeEmpty();
    });

    it('updates the updatedAt timestamp when renamed', function (): void {
        $user = User::create(UserId::generate(), Email::fromString('a@b.com'), UserName::fromString('AB'));
        $before = $user->updatedAt();

        // Sleep 1 microsecond to ensure a different timestamp.
        usleep(1000);
        $user->rename(UserName::fromString('New Name'));

        expect($user->name()->value())->toBe('New Name')
            ->and($user->updatedAt())->not->toBe($before);
    });

    it('deactivates and reactivates a user', function (): void {
        $user = User::create(UserId::generate(), Email::fromString('a@b.com'), UserName::fromString('AB'));

        $user->deactivate();
        expect($user->status())->toBe(UserStatus::Inactive);

        $user->activate();
        expect($user->status())->toBe(UserStatus::Active);
    });

    // ─── Additional coverage ─────────────────────────────────────────────────

    it('sets createdAt and updatedAt on create', function (): void {
        $before = new DateTimeImmutable();
        $user   = User::create(UserId::generate(), Email::fromString('a@b.com'), UserName::fromString('AB'));
        $after  = new DateTimeImmutable();

        expect($user->createdAt())->toBeInstanceOf(DateTimeImmutable::class)
            ->and($user->createdAt() >= $before)->toBeTrue()
            ->and($user->createdAt() <= $after)->toBeTrue()
            ->and($user->updatedAt())->toEqual($user->createdAt());
    });

    it('changes email and updates updatedAt', function (): void {
        $user   = User::create(UserId::generate(), Email::fromString('old@b.com'), UserName::fromString('AB'));
        $before = $user->updatedAt();

        usleep(1000);
        $newEmail = Email::fromString('new@b.com');
        $user->changeEmail($newEmail);

        expect($user->email()->value())->toBe('new@b.com')
            ->and($user->updatedAt())->not->toEqual($before);
    });

    it('exposes value objects via accessors', function (): void {
        $id    = UserId::generate();
        $email = Email::fromString('get@b.com');
        $name  = UserName::fromString('Getter Test');
        $user  = User::create($id, $email, $name);

        // Pull events so the aggregate is clean; accessor assertions are independent.
        $user->pullDomainEvents();

        expect($user->id())->toBeInstanceOf(App\Domain\Model\UserId::class)
            ->and($user->email())->toBeInstanceOf(App\Domain\Model\Email::class)
            ->and($user->name())->toBeInstanceOf(App\Domain\Model\UserName::class)
            ->and($user->status())->toBeInstanceOf(App\Domain\Model\UserStatus::class)
            ->and($user->id()->value())->toBe($id->value())
            ->and($user->email()->value())->toBe('get@b.com')
            ->and($user->name()->value())->toBe('Getter Test');
    });
});
