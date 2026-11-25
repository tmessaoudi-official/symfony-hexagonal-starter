<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Model;

use App\Domain\Event\UserCreated;
use App\Domain\Event\UserPromoted;
use App\Domain\Model\Email;
use App\Domain\Model\User;
use App\Domain\Model\UserId;
use App\Domain\Model\UserName;
use App\Domain\Model\UserRole;
use App\Domain\Model\UserStatus;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/** PHPUnit class on purpose (Infection gates src/Domain); see ValueObject/EmailTest. */
final class UserTest extends TestCase
{
    private function newUser(string $email = 'a@b.com', string $name = 'AB'): User
    {
        return User::create(UserId::generate(), Email::fromString($email), UserName::fromString($name));
    }

    #[Test]
    public function itCreatesAnActiveUserAndRecordsAUserCreatedEvent(): void
    {
        $id = UserId::generate();
        $email = Email::fromString('alice@example.com');
        $name = UserName::fromString('Alice Smith');

        $user = User::create($id, $email, $name);

        self::assertSame($id, $user->id());
        self::assertSame($email, $user->email());
        self::assertSame($name, $user->name());
        self::assertSame(UserStatus::Active, $user->status());

        $events = $user->pullDomainEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(UserCreated::class, $events[0]);
    }

    #[Test]
    public function itPullsEveryRecordedEventInOrder(): void
    {
        $user = $this->newUser();
        $user->promote(UserRole::Moderator);

        $events = $user->pullDomainEvents();

        self::assertCount(2, $events);
        self::assertInstanceOf(UserCreated::class, $events[0]);
        self::assertInstanceOf(UserPromoted::class, $events[1]);
    }

    #[Test]
    public function itClearsDomainEventsAfterPullingThem(): void
    {
        $user = $this->newUser();
        $user->pullDomainEvents();

        self::assertSame([], $user->pullDomainEvents());
    }

    #[Test]
    public function itDoesNotRecordAnEventWhenReconstituted(): void
    {
        $user = User::reconstitute(
            id: UserId::generate(),
            email: Email::fromString('a@b.com'),
            name: UserName::fromString('AB'),
            status: UserStatus::Active,
            role: UserRole::Regular,
            createdAt: new \DateTimeImmutable(),
            updatedAt: new \DateTimeImmutable(),
        );

        self::assertSame([], $user->pullDomainEvents());
    }

    #[Test]
    public function itUpdatesTheUpdatedAtTimestampWhenRenamed(): void
    {
        $user = $this->newUser();
        $before = $user->updatedAt();

        usleep(1000); // 1 ms, so the new timestamp differs
        $user->rename(UserName::fromString('New Name'));

        self::assertSame('New Name', $user->name()->value());
        self::assertNotEquals($before, $user->updatedAt());
    }

    #[Test]
    public function itDeactivatesAndReactivatesAUser(): void
    {
        $user = $this->newUser();

        $user->deactivate();
        self::assertSame(UserStatus::Inactive, $user->status());

        $user->activate();
        self::assertSame(UserStatus::Active, $user->status());
    }

    #[Test]
    public function itSetsCreatedAtAndUpdatedAtOnCreate(): void
    {
        $before = new \DateTimeImmutable();
        $user = $this->newUser();
        $after = new \DateTimeImmutable();

        self::assertGreaterThanOrEqual($before, $user->createdAt());
        self::assertLessThanOrEqual($after, $user->createdAt());
        self::assertEquals($user->createdAt(), $user->updatedAt());
    }

    #[Test]
    public function itChangesTheEmailAndUpdatesUpdatedAt(): void
    {
        $user = $this->newUser('old@b.com');
        $before = $user->updatedAt();

        usleep(1000);
        $user->changeEmail(Email::fromString('new@b.com'));

        self::assertSame('new@b.com', $user->email()->value());
        self::assertNotEquals($before, $user->updatedAt());
    }

    #[Test]
    public function itExposesItsValueObjectsThroughAccessors(): void
    {
        $id = UserId::generate();
        $user = User::create($id, Email::fromString('get@b.com'), UserName::fromString('Getter Test'));
        $user->pullDomainEvents();

        self::assertSame($id->value(), $user->id()->value());
        self::assertSame('get@b.com', $user->email()->value());
        self::assertSame('Getter Test', $user->name()->value());
        self::assertSame(UserStatus::Active, $user->status());
    }
}
