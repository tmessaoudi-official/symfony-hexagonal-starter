<?php

declare(strict_types=1);

use App\Application\Query\ListUsers\ListUsersQuery;
use App\Application\Query\ListUsers\ListUsersQueryHandler;
use App\Domain\Model\Email;
use App\Domain\Model\User;
use App\Domain\Model\UserId;
use App\Domain\Model\UserName;
use App\Domain\Model\UserRole;
use App\Domain\Model\UserStatus;
use App\Domain\Repository\UserRepository;
use PHPUnit\Framework\TestCase;

uses(TestCase::class);

describe('ListUsersQueryHandler (Unit)', function (): void {
    beforeEach(function (): void {
        $this->repo = $this->createMock(UserRepository::class);
        $this->handler = new ListUsersQueryHandler($this->repo);

        /* Helper that builds a minimal reconstituted User with a given email seed. */
        $this->makeUser = (static fn (string $emailSeed): User => User::reconstitute(
            id: UserId::generate(),
            email: Email::fromString("user{$emailSeed}@example.com"),
            name: UserName::fromString("User {$emailSeed}"),
            status: UserStatus::Active,
            role: UserRole::Regular,
            createdAt: new DateTimeImmutable(),
            updatedAt: new DateTimeImmutable(),
        ));
    });

    it('returns an empty result when no users exist', function (): void {
        $this->repo
            ->method('findPage')
            ->willReturn([]);

        $output = ($this->handler)(new ListUsersQuery(limit: 10));

        expect($output->items)->toBeEmpty()
            ->and($output->hasMore)->toBeFalse()
            ->and($output->nextCursor)->toBeNull();
    });

    it('returns all items and hasMore=false when result count equals limit', function (): void {
        $make = $this->makeUser;
        $users = [$make('1'), $make('2'), $make('3')];

        // Handler requests limit + 1 = 4 rows; repo returns 3 → no next page.
        $this->repo
            ->expects($this->once())
            ->method('findPage')
            ->with(4, $this->isNull())
            ->willReturn($users);

        $output = ($this->handler)(new ListUsersQuery(limit: 3));

        expect($output->items)->toHaveCount(3)
            ->and($output->hasMore)->toBeFalse()
            ->and($output->nextCursor)->toBeNull();
    });

    it('sets hasMore=true and provides nextCursor when more items exist', function (): void {
        // Simulate 6 items in DB with limit 5: handler fetches 6, sees 6 > 5 → hasMore.
        $make = $this->makeUser;
        $users = [];
        for ($i = 1; $i <= 6; ++$i) {
            $users[] = $make((string) $i);
        }

        $this->repo
            ->expects($this->once())
            ->method('findPage')
            ->with(6, $this->isNull())
            ->willReturn($users);

        $output = ($this->handler)(new ListUsersQuery(limit: 5));

        // The extra (6th) row only signals the next page: the page is rows 1-5, and the
        // cursor is the last row actually returned (row 5), not the probe row.
        expect($output->items)->toHaveCount(5)
            ->and($output->hasMore)->toBeTrue()
            ->and($output->items[0]->id)->toBe($users[0]->id()->value())
            ->and($output->nextCursor)->toBe($users[4]->id()->value());
    });

    it('passes the cursor UserId to the repository when afterCursor is set', function (): void {
        $make = $this->makeUser;
        $cursorId = UserId::generate();

        $this->repo
            ->expects($this->once())
            ->method('findPage')
            ->with(
                3,
                $this->callback(fn (UserId $id): bool => $id->value() === $cursorId->value()),
            )
            ->willReturn([$make('next')]);

        ($this->handler)(new ListUsersQuery(limit: 2, afterCursor: $cursorId->value()));
    });

    it('maps domain users to UserOutput DTOs correctly', function (): void {
        $make = $this->makeUser;
        $user = $make('mapped');

        $this->repo->method('findPage')->willReturn([$user]);

        $output = ($this->handler)(new ListUsersQuery(limit: 5));

        expect($output->items)->toHaveCount(1);
        $item = $output->items[0];
        expect($item->id)->toBe($user->id()->value())
            ->and($item->email)->toBe($user->email()->value())
            ->and($item->name)->toBe($user->name()->value())
            ->and($item->status)->toBe($user->status()->value);
    });

    it('defaults the page size to 20', function (): void {
        expect(new ListUsersQuery()->limit)->toBe(20);
    });

    it('accepts the boundary limits 1 and 100', function (int $limit): void {
        expect(new ListUsersQuery(limit: $limit)->limit)->toBe($limit);
    })->with([1, 100]);

    it('throws InvalidArgumentException when limit is below 1', function (): void {
        new ListUsersQuery(limit: 0);
    })->throws(InvalidArgumentException::class, 'Limit must be between 1 and 100');

    it('throws InvalidArgumentException when limit exceeds 100', function (): void {
        new ListUsersQuery(limit: 101);
    })->throws(InvalidArgumentException::class, 'Limit must be between 1 and 100');
});
