<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * UserEntity — Doctrine ORM entity for the users table.
 *
 * WHY a SEPARATE class from Domain\Model\User?
 * - Doctrine imposes its own concerns: lazy loading proxies, change tracking, lifecycle callbacks.
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
#[ORM\Entity(repositoryClass: \App\Infrastructure\Persistence\Doctrine\Repository\DoctrineUserRepository::class)]
#[ORM\Table(name: 'users')]
#[ORM\UniqueConstraint(name: 'users_email_unique', columns: ['email'])]
#[ORM\HasLifecycleCallbacks]
class UserEntity
{
    #[ORM\Id]
    #[ORM\Column(type: 'guid', unique: true)]
    private string $id;

    #[ORM\Column(type: 'string', length: 254, unique: true)]
    private string $email;

    #[ORM\Column(type: 'string', length: 100)]
    private string $name;

    #[ORM\Column(type: 'string', length: 20, enumType: \App\Domain\Model\UserStatus::class)]
    private \App\Domain\Model\UserStatus $status;

    #[ORM\Column(type: 'datetime_immutable')]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private DateTimeImmutable $updatedAt;

    // No-args constructor required by Doctrine for proxy instantiation.
    public function __construct(
        string $id,
        string $email,
        string $name,
        \App\Domain\Model\UserStatus $status,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $updatedAt,
    ) {
        $this->id        = $id;
        $this->email     = $email;
        $this->name      = $name;
        $this->status    = $status;
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
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

    public function getStatus(): \App\Domain\Model\UserStatus
    {
        return $this->status;
    }

    public function setStatus(\App\Domain\Model\UserStatus $status): void
    {
        $this->status = $status;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(DateTimeImmutable $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }
}
