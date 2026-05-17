<?php

declare(strict_types=1);

use App\Domain\Model\Email;
use App\Domain\Model\User;
use App\Domain\Model\UserId;
use App\Domain\Model\UserName;
use App\Domain\Model\UserRole;
use App\Domain\Model\UserStatus;
use App\Domain\Repository\UserRepository;
use App\Domain\Service\UserPromotionPolicy;
use DateTimeImmutable;

// ─── In-memory fake — zero framework, zero database ──────────────────────────
// This is an implementation of the Domain port (UserRepository interface).
// It proves that the port can be satisfied by anything — Doctrine, Redis,
// an array in a test. The policy doesn't know and doesn't care.

final class InMemoryUserRepository implements UserRepository
{
    /** @param list<User> $users */
    public function __construct(private array $users = []) {}

    public function save(User $user): void { $this->users[] = $user; }

    public function getById(UserId $id): User
    {
        foreach ($this->users as $u) {
            if ($u->id()->equals($id)) {
                return $u;
            }
        }
        throw new \RuntimeException('Not found');
    }

    public function findById(UserId $id): ?User
    {
        foreach ($this->users as $u) {
            if ($u->id()->equals($id)) {
                return $u;
            }
        }
        return null;
    }

    public function findByEmail(Email $email): ?User
    {
        foreach ($this->users as $u) {
            if ($u->email()->equals($email)) {
                return $u;
            }
        }
        return null;
    }

    public function existsByEmail(Email $email): bool
    {
        return $this->findByEmail($email) !== null;
    }

    /** @return list<User> */
    public function findAll(): array { return $this->users; }

    /** @return list<User> */
    public function findPage(int $limit, ?\App\Domain\Model\UserId $cursor = null): array { return []; }

    public function delete(User $user): void
    {
        $this->users = array_values(array_filter($this->users, fn ($u) => !$u->id()->equals($user->id())));
    }

    public function countByRole(UserRole $role): int
    {
        return count(array_filter($this->users, fn ($u) => $u->role() === $role));
    }
}

// ─── Helpers ─────────────────────────────────────────────────────────────────

function makeUser(
    UserRole $role = UserRole::Regular,
    UserStatus $status = UserStatus::Active,
    int $ageDays = 60,
): User {
    $createdAt = (new DateTimeImmutable())->modify("-{$ageDays} days");
    return User::reconstitute(
        id: UserId::generate(),
        email: Email::fromString(uniqid('user') . '@example.com'),
        name: UserName::fromString('Test User'),
        status: $status,
        role: $role,
        createdAt: $createdAt,
        updatedAt: $createdAt,
    );
}

// ─── Tests ───────────────────────────────────────────────────────────────────

describe('UserPromotionPolicy', function (): void {
    beforeEach(function (): void {
        $this->policy = new UserPromotionPolicy();
        $this->repo   = new InMemoryUserRepository();
        $this->now    = new DateTimeImmutable();
    });

    // ── Rule 1: active status ────────────────────────────────────────────────

    it('denies promotion of an inactive user', function (): void {
        $user   = makeUser(UserRole::Regular, UserStatus::Inactive, ageDays: 60);
        $result = $this->policy->check($user, UserRole::Moderator, $this->repo, $this->now);

        expect($result->isEligible())->toBeFalse()
            ->and($result->reason())->toContain('not active');
    });

    // ── Rule 2: sequential progression ──────────────────────────────────────

    it('denies skipping from Regular directly to Admin', function (): void {
        $user   = makeUser(UserRole::Regular, ageDays: 200);
        $result = $this->policy->check($user, UserRole::Admin, $this->repo, $this->now);

        expect($result->isEligible())->toBeFalse()
            ->and($result->reason())->toContain('sequential');
    });

    it('denies demotion from Admin to Regular', function (): void {
        $user   = makeUser(UserRole::Admin, ageDays: 200);
        $result = $this->policy->check($user, UserRole::Regular, $this->repo, $this->now);

        expect($result->isEligible())->toBeFalse();
    });

    it('denies demotion from Moderator to Regular', function (): void {
        $user   = makeUser(UserRole::Moderator, ageDays: 200);
        $result = $this->policy->check($user, UserRole::Regular, $this->repo, $this->now);

        expect($result->isEligible())->toBeFalse();
    });

    // ── Rule 3: account age ──────────────────────────────────────────────────

    it('denies Moderator promotion when account is under 30 days old', function (): void {
        $user   = makeUser(UserRole::Regular, ageDays: 15);
        $result = $this->policy->check($user, UserRole::Moderator, $this->repo, $this->now);

        expect($result->isEligible())->toBeFalse()
            ->and($result->reason())->toContain('30');
    });

    it('denies Admin promotion when account is under 90 days old', function (): void {
        $user   = makeUser(UserRole::Moderator, ageDays: 45);
        $result = $this->policy->check($user, UserRole::Admin, $this->repo, $this->now);

        expect($result->isEligible())->toBeFalse()
            ->and($result->reason())->toContain('90');
    });

    it('allows Regular→Moderator for an active account older than 30 days', function (): void {
        $user   = makeUser(UserRole::Regular, ageDays: 31);
        $result = $this->policy->check($user, UserRole::Moderator, $this->repo, $this->now);

        expect($result->isEligible())->toBeTrue();
    });

    it('allows Moderator→Admin for an active account older than 90 days', function (): void {
        $user   = makeUser(UserRole::Moderator, ageDays: 91);
        $result = $this->policy->check($user, UserRole::Admin, $this->repo, $this->now);

        expect($result->isEligible())->toBeTrue();
    });

    // ── Rule 4: Admin cap (cross-aggregate — uses repository) ────────────────

    it('denies Admin promotion when the Admin cap of 5 is reached', function (): void {
        // Populate the repo with 5 existing Admins
        for ($i = 0; $i < 5; $i++) {
            $this->repo->save(makeUser(UserRole::Admin, ageDays: 200));
        }

        $candidate = makeUser(UserRole::Moderator, ageDays: 91);
        $result    = $this->policy->check($candidate, UserRole::Admin, $this->repo, $this->now);

        expect($result->isEligible())->toBeFalse()
            ->and($result->reason())->toContain('5');
    });

    it('allows Admin promotion when fewer than 5 Admins exist', function (): void {
        // Only 4 existing Admins
        for ($i = 0; $i < 4; $i++) {
            $this->repo->save(makeUser(UserRole::Admin, ageDays: 200));
        }

        $candidate = makeUser(UserRole::Moderator, ageDays: 91);
        $result    = $this->policy->check($candidate, UserRole::Admin, $this->repo, $this->now);

        expect($result->isEligible())->toBeTrue();
    });
});
