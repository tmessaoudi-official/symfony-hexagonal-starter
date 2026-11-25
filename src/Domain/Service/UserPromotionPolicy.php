<?php

declare(strict_types=1);

namespace App\Domain\Service;

use App\Domain\Model\User;
use App\Domain\Model\UserRole;
use App\Domain\Repository\UserRepository;

/**
 * UserPromotionPolicy — pure domain decision logic.
 *
 * WHY a Domain Service (Policy) and not a method on User?
 * - Rules 1–3 could live on User (single-aggregate checks).
 * - Rule 4 (Admin cap) requires querying the repository — it spans beyond the aggregate.
 *   Once a rule needs a port, the whole policy belongs in a Domain Service.
 * - Passing the repository as a method parameter (not constructor injection) keeps the
 *   Policy stateless and freely instantiable with no DI container.
 *
 * Zero imports from Symfony\, Doctrine\, or any infrastructure layer.
 */
final class UserPromotionPolicy
{
    private const int MAX_ADMINS = 5;
    private const int MIN_AGE_MODERATOR_DAYS = 30;
    private const int MIN_AGE_ADMIN_DAYS = 90;

    /**
     * Checks all eligibility rules for promoting $user to $targetRole.
     *
     * Rules (all must pass):
     *   1. User must be ACTIVE
     *   2. Promotion must be sequential: Regular→Moderator→Admin (no skipping, no demotion)
     *   3. Minimum account age: 30 days for Moderator, 90 days for Admin
     *   4. Admin cap: no more than 5 Admins in the system at any time
     */
    public function check(
        User $user,
        UserRole $targetRole,
        UserRepository $repository,
        \DateTimeImmutable $now,
    ): EligibilityResult {
        if (!$user->status()->isActive()) {
            return EligibilityResult::ineligible('User account is not active.');
        }

        if (!$targetRole->isDirectPromotionOf($user->role())) {
            return EligibilityResult::ineligible(sprintf(
                'Cannot promote from %s to %s — role progression must be sequential.',
                $user->role()->name,
                $targetRole->name,
            ));
        }

        $ageDays = (int) $user->createdAt()->diff($now)->days;
        $minAge = match ($targetRole) {
            UserRole::Moderator => self::MIN_AGE_MODERATOR_DAYS,
            UserRole::Admin => self::MIN_AGE_ADMIN_DAYS,
            default => 0,
        };

        if ($ageDays < $minAge) {
            return EligibilityResult::ineligible(sprintf(
                'Account must be at least %d days old to become %s (currently %d days).',
                $minAge,
                $targetRole->name,
                $ageDays,
            ));
        }

        if ($targetRole === UserRole::Admin && $repository->countByRole(UserRole::Admin) >= self::MAX_ADMINS) {
            return EligibilityResult::ineligible(sprintf(
                'The maximum number of Admins (%d) has been reached.',
                self::MAX_ADMINS,
            ));
        }

        return EligibilityResult::eligible();
    }
}
