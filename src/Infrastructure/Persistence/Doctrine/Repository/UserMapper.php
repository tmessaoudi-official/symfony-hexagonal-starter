<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Repository;

use App\Domain\Model\Email;
use App\Domain\Model\User;
use App\Domain\Model\UserId;
use App\Domain\Model\UserName;
use App\Infrastructure\Persistence\Doctrine\Entity\UserEntity;

/**
 * UserMapper — translates between the Domain Model and the Doctrine Entity.
 *
 * WHY a dedicated mapper class?
 * - The alternative is static methods on the entity or on the domain model.
 *   Both options create coupling: entities would know about domain models, or vice versa.
 * - A dedicated mapper keeps both classes clean and makes the translation explicit.
 * - It's the only place that "knows" both representations — a deliberate seam.
 *
 * WHY not use AutoMapper or Symfony Serializer here?
 * - Mapping at this layer is structural (field names and types differ),
 *   not just serialisation. Explicit mapping is clearer and statically analysable.
 */
final class UserMapper
{
    public function toEntity(User $user): UserEntity
    {
        return new UserEntity(
            id: $user->id()->value(),
            email: $user->email()->value(),
            name: $user->name()->value(),
            status: $user->status(),
            createdAt: $user->createdAt(),
            updatedAt: $user->updatedAt(),
        );
    }

    public function updateEntity(UserEntity $entity, User $user): void
    {
        $entity->setEmail($user->email()->value());
        $entity->setName($user->name()->value());
        $entity->setStatus($user->status());
        $entity->setUpdatedAt($user->updatedAt());
    }

    public function toDomain(UserEntity $entity): User
    {
        return User::reconstitute(
            id: UserId::fromString($entity->getId()),
            email: Email::fromString($entity->getEmail()),
            name: UserName::fromString($entity->getName()),
            status: $entity->getStatus(),
            createdAt: $entity->getCreatedAt(),
            updatedAt: $entity->getUpdatedAt(),
        );
    }
}
