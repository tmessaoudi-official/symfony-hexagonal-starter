<?php

declare(strict_types=1);

namespace App\Tests\Factory;

use App\Domain\Model\UserStatus;
use App\Infrastructure\Persistence\Doctrine\Entity\UserEntity;
use DateTimeImmutable;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * UserFactory — Foundry v2 factory for UserEntity.
 *
 * Targets the Doctrine entity (not the Domain aggregate) because Foundry
 * operates through the ORM persistence layer.
 *
 * Default state: a valid, active user with a unique email and name.
 * Use ->inactive() to switch to Inactive status.
 *
 * @extends PersistentProxyObjectFactory<UserEntity>
 */
final class UserFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return UserEntity::class;
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaults(): array
    {
        // Use uniqid with more_entropy=true for collision-resistant unique values
        // across parallel test runs. This avoids shared static state.
        $unique = uniqid('', true);
        $now    = new DateTimeImmutable();

        // Build a valid UUID v4 from random bytes.
        $bytes    = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0F) | 0x40); // version 4
        $bytes[8] = chr((ord($bytes[8]) & 0x3F) | 0x80); // variant
        $uuid     = vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));

        return [
            'id'        => $uuid,
            'email'     => sprintf('user-%s@example.com', substr(md5($unique), 0, 12)),
            'name'      => sprintf('User %s', substr($unique, 0, 6)),
            'status'    => UserStatus::Active,
            'createdAt' => $now,
            'updatedAt' => $now,
        ];
    }

    /**
     * State: inactive user.
     */
    public function inactive(): static
    {
        return $this->with(['status' => UserStatus::Inactive]);
    }

    protected function initialize(): static
    {
        return $this;
    }
}
