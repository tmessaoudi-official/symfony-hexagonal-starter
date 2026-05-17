<?php

declare(strict_types=1);

use App\Application\DTO\Output\UserOutput;
use App\Application\Query\GetUser\GetUserQuery;
use App\Application\Query\GetUser\GetUserQueryHandler;
use App\Domain\Exception\UserNotFoundException;
use App\Domain\Model\Email;
use App\Domain\Model\User;
use App\Domain\Model\UserId;
use App\Domain\Model\UserName;
use App\Domain\Model\UserRole;
use App\Domain\Model\UserStatus;
use App\Domain\Repository\UserRepository;
use DateTimeImmutable;
use PHPUnit\Framework\MockObject\MockObject;

uses(\PHPUnit\Framework\TestCase::class);

/**
 * @var UserRepository&MockObject $repo
 * @var GetUserQueryHandler $handler
 */

describe('GetUserQueryHandler (Unit)', function (): void {
    beforeEach(function (): void {
        $this->repo    = $this->createMock(UserRepository::class);
        $this->handler = new GetUserQueryHandler($this->repo);
    });

    it('returns a UserOutput DTO when the user exists', function (): void {
        $id        = UserId::generate();
        $createdAt = new DateTimeImmutable('2024-01-01T00:00:00+00:00');
        $updatedAt = new DateTimeImmutable('2024-06-01T00:00:00+00:00');

        $user = User::reconstitute(
            id: $id,
            email: Email::fromString('alice@example.com'),
            name: UserName::fromString('Alice Smith'),
            status: UserStatus::Active,
            role: UserRole::Regular,
            createdAt: $createdAt,
            updatedAt: $updatedAt,
        );

        $this->repo
            ->method('getById')
            ->with($this->callback(fn (UserId $arg): bool => $arg->value() === $id->value()))
            ->willReturn($user);

        $query  = new GetUserQuery(userId: $id->value());
        $output = ($this->handler)($query);

        expect($output)->toBeInstanceOf(UserOutput::class)
            ->and($output->id)->toBe($id->value())
            ->and($output->email)->toBe('alice@example.com')
            ->and($output->name)->toBe('Alice Smith')
            ->and($output->status)->toBe('active')
            ->and($output->createdAt)->toEqual($createdAt)
            ->and($output->updatedAt)->toEqual($updatedAt);
    });

    it('propagates UserNotFoundException when the user does not exist', function (): void {
        $missingId = UserId::generate();

        $this->repo
            ->method('getById')
            ->willThrowException(UserNotFoundException::withId($missingId));

        ($this->handler)(new GetUserQuery(userId: $missingId->value()));
    })->throws(UserNotFoundException::class);

    it('returns the inactive status correctly', function (): void {
        $id   = UserId::generate();
        $now  = new DateTimeImmutable();

        $user = User::reconstitute(
            id: $id,
            email: Email::fromString('inactive@example.com'),
            name: UserName::fromString('Inactive User'),
            status: UserStatus::Inactive,
            role: UserRole::Regular,
            createdAt: $now,
            updatedAt: $now,
        );

        $this->repo->method('getById')->willReturn($user);

        $output = ($this->handler)(new GetUserQuery(userId: $id->value()));

        expect($output->status)->toBe('inactive');
    });
});
