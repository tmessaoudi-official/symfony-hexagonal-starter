<?php

declare(strict_types=1);

use App\Domain\Exception\UserNotFoundException;
use App\Domain\Model\Email;
use App\Domain\Model\User;
use App\Domain\Model\UserId;
use App\Domain\Model\UserName;
use App\Domain\Model\UserStatus;
use App\Domain\Repository\UserRepository;

/**
 * Integration tests for DoctrineUserRepository.
 *
 * Runs inside a DAMA DoctrineTestBundle transaction — every test starts with
 * a clean database and all writes are rolled back after the test.
 */
describe('DoctrineUserRepository (Integration)', function (): void {
    beforeEach(function (): void {
        /** @var UserRepository $repo */
        $this->repo = $this->getContainer()->get(UserRepository::class);
    });

    it('saves a user and finds it by ID', function (): void {
        $id   = UserId::generate();
        $user = User::create($id, Email::fromString('repo@example.com'), UserName::fromString('Repo User'));
        $user->pullDomainEvents(); // discard events — not needed in this test

        $this->repo->save($user);

        $found = $this->repo->findById($id);

        expect($found)->not->toBeNull()
            ->and($found->id()->value())->toBe($id->value())
            ->and($found->email()->value())->toBe('repo@example.com')
            ->and($found->name()->value())->toBe('Repo User')
            ->and($found->status())->toBe(UserStatus::Active);
    });

    it('findById returns null for a non-existent ID', function (): void {
        $missing = $this->repo->findById(UserId::generate());

        expect($missing)->toBeNull();
    });

    it('getById throws UserNotFoundException for a non-existent ID', function (): void {
        $id = UserId::generate();
        $this->repo->getById($id);
    })->throws(UserNotFoundException::class);

    it('saves a user and finds it by email', function (): void {
        $user = User::create(UserId::generate(), Email::fromString('findbyemail@example.com'), UserName::fromString('By Email'));
        $user->pullDomainEvents();

        $this->repo->save($user);

        $found = $this->repo->findByEmail(Email::fromString('findbyemail@example.com'));

        expect($found)->not->toBeNull()
            ->and($found->email()->value())->toBe('findbyemail@example.com');
    });

    it('findByEmail returns null when the email does not exist', function (): void {
        $result = $this->repo->findByEmail(Email::fromString('ghost@example.com'));

        expect($result)->toBeNull();
    });

    it('existsByEmail returns true for a saved user', function (): void {
        $user = User::create(UserId::generate(), Email::fromString('exists@example.com'), UserName::fromString('Exists'));
        $user->pullDomainEvents();

        $this->repo->save($user);

        expect($this->repo->existsByEmail(Email::fromString('exists@example.com')))->toBeTrue();
    });

    it('existsByEmail returns false when no user has that email', function (): void {
        expect($this->repo->existsByEmail(Email::fromString('nobody@example.com')))->toBeFalse();
    });

    it('findAll returns all saved users', function (): void {
        $this->repo->save(User::create(UserId::generate(), Email::fromString('all1@example.com'), UserName::fromString('All One')));
        $this->repo->save(User::create(UserId::generate(), Email::fromString('all2@example.com'), UserName::fromString('All Two')));

        $all = $this->repo->findAll();

        // There may be other users from previous non-rolled-back seeds — at minimum 2.
        expect(count($all))->toBeGreaterThanOrEqual(2);
    });

    it('updates a user when saved twice with the same ID', function (): void {
        $id   = UserId::generate();
        $user = User::create($id, Email::fromString('updateme@example.com'), UserName::fromString('Old Name'));
        $user->pullDomainEvents();
        $this->repo->save($user);

        $user->rename(UserName::fromString('New Name'));
        $this->repo->save($user);

        $found = $this->repo->findById($id);

        expect($found)->not->toBeNull()
            ->and($found->name()->value())->toBe('New Name');
    });

    it('cursor pagination via findPage returns correct slice', function (): void {
        // Create 5 users with predictable IDs in ascending order.
        $ids = [];
        for ($i = 1; $i <= 5; $i++) {
            $id   = UserId::generate();
            $ids[] = $id->value();
            $user = User::create($id, Email::fromString("page{$i}@example.com"), UserName::fromString("Page {$i}"));
            $user->pullDomainEvents();
            $this->repo->save($user);
        }

        // Fetch the first page (limit 2).
        $page1 = $this->repo->findPage(2);

        expect($page1)->toHaveCount(2);

        // Fetch next page using the last item of page 1 as cursor.
        $cursor = $page1[count($page1) - 1]->id();
        $page2  = $this->repo->findPage(2, $cursor);

        expect($page2)->toHaveCount(2);

        // IDs on page 2 must all be > cursor.
        foreach ($page2 as $user) {
            expect($user->id()->value() > $cursor->value())->toBeTrue();
        }
    });

    it('deletes a user', function (): void {
        $id   = UserId::generate();
        $user = User::create($id, Email::fromString('deleteme@example.com'), UserName::fromString('Delete Me'));
        $user->pullDomainEvents();
        $this->repo->save($user);

        $this->repo->delete($user);

        $found = $this->repo->findById($id);
        expect($found)->toBeNull();
    });
});
