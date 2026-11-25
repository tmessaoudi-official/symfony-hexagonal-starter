<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Service;

use App\Domain\Model\Email;
use App\Domain\Model\User;
use App\Domain\Model\UserId;
use App\Domain\Model\UserName;
use App\Domain\Model\UserRole;
use App\Domain\Model\UserStatus;
use App\Domain\Service\UserPromotionPolicy;
use App\Tests\Support\InMemoryUserRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/** PHPUnit class on purpose (Infection gates src/Domain); see ValueObject/EmailTest. */
final class UserPromotionPolicyTest extends TestCase
{
    private UserPromotionPolicy $policy;
    private InMemoryUserRepository $repo;
    private \DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->policy = new UserPromotionPolicy();
        $this->repo = new InMemoryUserRepository();
        $this->now = new \DateTimeImmutable();
    }

    private function makeUser(UserRole $role = UserRole::Regular, UserStatus $status = UserStatus::Active, int $ageDays = 60): User
    {
        $createdAt = $this->now->modify("-{$ageDays} days");

        return User::reconstitute(
            id: UserId::generate(),
            email: Email::fromString(uniqid('user', true).'@example.com'),
            name: UserName::fromString('Test User'),
            status: $status,
            role: $role,
            createdAt: $createdAt,
            updatedAt: $createdAt,
        );
    }

    // ── Rule 1: active status ────────────────────────────────────────────────

    #[Test]
    public function itDeniesPromotionOfAnInactiveUser(): void
    {
        $result = $this->policy->check($this->makeUser(UserRole::Regular, UserStatus::Inactive), UserRole::Moderator, $this->repo, $this->now);

        self::assertFalse($result->isEligible());
        self::assertStringContainsString('not active', (string) $result->reason());
    }

    // ── Rule 2: sequential progression ──────────────────────────────────────

    #[Test]
    public function itDeniesSkippingFromRegularDirectlyToAdmin(): void
    {
        $result = $this->policy->check($this->makeUser(UserRole::Regular, ageDays: 200), UserRole::Admin, $this->repo, $this->now);

        self::assertFalse($result->isEligible());
        self::assertStringContainsString('sequential', (string) $result->reason());
    }

    #[Test]
    public function itDeniesDemotionFromAdminToRegular(): void
    {
        self::assertFalse($this->policy->check($this->makeUser(UserRole::Admin, ageDays: 200), UserRole::Regular, $this->repo, $this->now)->isEligible());
    }

    #[Test]
    public function itDeniesDemotionFromModeratorToRegular(): void
    {
        self::assertFalse($this->policy->check($this->makeUser(UserRole::Moderator, ageDays: 200), UserRole::Regular, $this->repo, $this->now)->isEligible());
    }

    // ── Rule 3: account age ──────────────────────────────────────────────────

    #[Test]
    public function itDeniesModeratorPromotionWhenTheAccountIsUnder30DaysOld(): void
    {
        $result = $this->policy->check($this->makeUser(UserRole::Regular, ageDays: 15), UserRole::Moderator, $this->repo, $this->now);

        self::assertFalse($result->isEligible());
        self::assertStringContainsString('30', (string) $result->reason());
    }

    #[Test]
    public function itDeniesAdminPromotionWhenTheAccountIsUnder90DaysOld(): void
    {
        $result = $this->policy->check($this->makeUser(UserRole::Moderator, ageDays: 45), UserRole::Admin, $this->repo, $this->now);

        self::assertFalse($result->isEligible());
        self::assertStringContainsString('90', (string) $result->reason());
    }

    #[Test]
    public function itAllowsRegularToModeratorForAnActiveAccountOlderThan30Days(): void
    {
        self::assertTrue($this->policy->check($this->makeUser(UserRole::Regular, ageDays: 31), UserRole::Moderator, $this->repo, $this->now)->isEligible());
    }

    #[Test]
    public function itAllowsModeratorToAdminForAnActiveAccountOlderThan90Days(): void
    {
        self::assertTrue($this->policy->check($this->makeUser(UserRole::Moderator, ageDays: 91), UserRole::Admin, $this->repo, $this->now)->isEligible());
    }

    /** The minimum age is inclusive: an account exactly 30 (or 90) days old qualifies. */
    #[Test]
    public function itAllowsPromotionOnTheExactMinimumAccountAge(): void
    {
        self::assertTrue($this->policy->check($this->makeUser(UserRole::Regular, ageDays: 30), UserRole::Moderator, $this->repo, $this->now)->isEligible());
        self::assertTrue($this->policy->check($this->makeUser(UserRole::Moderator, ageDays: 90), UserRole::Admin, $this->repo, $this->now)->isEligible());
    }

    // ── Rule 4: Admin cap (cross-aggregate, needs the repository port) ───────

    #[Test]
    public function itDeniesAdminPromotionWhenTheCapOf5AdminsIsReached(): void
    {
        for ($i = 0; $i < 5; ++$i) {
            $this->repo->save($this->makeUser(UserRole::Admin, ageDays: 200));
        }

        $result = $this->policy->check($this->makeUser(UserRole::Moderator, ageDays: 91), UserRole::Admin, $this->repo, $this->now);

        self::assertFalse($result->isEligible());
        self::assertStringContainsString('5', (string) $result->reason());
    }

    #[Test]
    public function itAllowsAdminPromotionWhenFewerThan5AdminsExist(): void
    {
        for ($i = 0; $i < 4; ++$i) {
            $this->repo->save($this->makeUser(UserRole::Admin, ageDays: 200));
        }

        self::assertTrue($this->policy->check($this->makeUser(UserRole::Moderator, ageDays: 91), UserRole::Admin, $this->repo, $this->now)->isEligible());
    }
}
