<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Entity;

use App\Domain\Model\UserRole;
use App\Domain\Model\UserStatus;
use Doctrine\ORM\Mapping as ORM;

/**
 * UserEntity — Doctrine ORM entity for the users table.
 *
 * WHY a SEPARATE class from Domain\Model\User?
 * - Doctrine imposes its own concerns: lazy loading, change tracking, lifecycle callbacks.
 * - These concerns must not pollute the Domain model.
 * - Keeping them separate means the domain entity can use readonly properties, final classes,
 *   and value objects freely — Doctrine doesn't need to reflect on them.
 * - The UserMapper translates between the two representations.
 *
 * WHY use string for status instead of the UserStatus enum directly?
 * - Doctrine 3.x can map backed enums natively. The 'enumType' ORM option handles the translation.
 *   We use the string column and let Doctrine convert via the enumType attribute,
 *   keeping the entity aware of the enum (which IS in the Domain) only via import.
 *   This is an acceptable coupling point — the entity is Infrastructure.
 */
// No repositoryClass: DoctrineUserRepository is the hexagonal adapter (it implements the
// Domain port), not a Doctrine EntityRepository. Naming it here made Doctrine instantiate the
// adapter with (EntityManager, ClassMetadata) and fail on every getRepository() call.
#[ORM\Entity]
#[ORM\Table(name: 'users')]
// Same constraint name as the migration (uq_ prefix, like ck_ for CHECKs): a different name here
// makes Doctrine see schema drift and generate a rename in every migrations:diff.
#[ORM\UniqueConstraint(name: 'uq_users_email', columns: ['email'])]
// Mirrors the index the migration creates (keyset pagination and "newest users" sorts).
#[ORM\Index(name: 'idx_users_created_at', columns: ['created_at'])]
#[ORM\HasLifecycleCallbacks]
class UserEntity
{
    // Doctrine never calls this constructor when it loads a row (it instantiates without it);
    // only the UserMapper does, when it turns a Domain User into an entity.
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'guid')]
        private string $id,
        #[ORM\Column(type: 'string', length: 254)]
        private string $email,
        #[ORM\Column(type: 'string', length: 100)]
        private string $name,
        #[ORM\Column(type: 'string', length: 20, enumType: UserStatus::class, options: ['default' => 'active'])]
        private UserStatus $status,
        #[ORM\Column(type: 'string', length: 20, enumType: UserRole::class, options: ['default' => 'regular'])]
        private UserRole $role,
        #[ORM\Column(type: 'datetime_immutable')]
        private \DateTimeImmutable $createdAt,
        #[ORM\Column(type: 'datetime_immutable')]
        private \DateTimeImmutable $updatedAt,
    ) {
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): void
    {
        $this->email = $email;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getStatus(): UserStatus
    {
        return $this->status;
    }

    public function setStatus(UserStatus $status): void
    {
        $this->status = $status;
    }

    public function getRole(): UserRole
    {
        return $this->role;
    }

    public function setRole(UserRole $role): void
    {
        $this->role = $role;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }
}
