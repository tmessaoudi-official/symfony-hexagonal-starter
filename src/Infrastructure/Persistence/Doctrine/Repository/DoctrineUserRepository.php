<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Repository;

use App\Domain\Exception\UserNotFoundException;
use App\Domain\Model\Email;
use App\Domain\Model\User;
use App\Domain\Model\UserId;
use App\Domain\Repository\UserRepository;
use App\Infrastructure\Persistence\Doctrine\Entity\UserEntity;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Exception\NoResultException;

/**
 * DoctrineUserRepository — the Doctrine implementation of UserRepository.
 *
 * WHY extend ServiceEntityRepository is NOT done here:
 * - We want to depend on EntityManagerInterface, not on Doctrine's convenience repository.
 * - This makes testing easier (mock the EM directly) and makes the dependency explicit.
 * - Doctrine's ServiceEntityRepository is a convenience for simple apps;
 *   it creates tight coupling in hexagonal architectures.
 *
 * Implements cursor pagination — see docs/patterns/cursor-pagination.md.
 */
final class DoctrineUserRepository implements UserRepository
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserMapper $mapper,
    ) {}

    public function save(User $user): void
    {
        $existing = $this->em->find(UserEntity::class, $user->id()->value());

        if ($existing === null) {
            $entity = $this->mapper->toEntity($user);
            $this->em->persist($entity);
        } else {
            $this->mapper->updateEntity($existing, $user);
        }

        $this->em->flush();
    }

    public function getById(UserId $id): User
    {
        $entity = $this->em->find(UserEntity::class, $id->value());

        if ($entity === null) {
            throw UserNotFoundException::withId($id);
        }

        return $this->mapper->toDomain($entity);
    }

    public function findById(UserId $id): ?User
    {
        $entity = $this->em->find(UserEntity::class, $id->value());

        return $entity !== null ? $this->mapper->toDomain($entity) : null;
    }

    public function findByEmail(Email $email): ?User
    {
        $entity = $this->em->getRepository(UserEntity::class)
            ->findOneBy(['email' => $email->value()]);

        return $entity !== null ? $this->mapper->toDomain($entity) : null;
    }

    public function existsByEmail(Email $email): bool
    {
        try {
            $count = (int) $this->em->createQueryBuilder()
                ->select('COUNT(u.id)')
                ->from(UserEntity::class, 'u')
                ->where('u.email = :email')
                ->setParameter('email', $email->value())
                ->getQuery()
                ->getSingleScalarResult();

            return $count > 0;
        } catch (NoResultException) {
            return false;
        }
    }

    /**
     * @return list<User>
     */
    public function findAll(): array
    {
        $entities = $this->em->getRepository(UserEntity::class)->findAll();

        return array_map($this->mapper->toDomain(...), $entities);
    }

    /**
     * Cursor-based pagination.
     *
     * Uses WHERE id > :cursor ORDER BY id LIMIT :limit.
     * Always O(LIMIT) scan cost regardless of page number.
     *
     * @return list<User>
     */
    public function findPage(int $limit, ?UserId $cursor = null): array
    {
        $qb = $this->em->createQueryBuilder()
            ->select('u')
            ->from(UserEntity::class, 'u')
            ->orderBy('u.id', 'ASC')
            ->setMaxResults($limit);

        if ($cursor !== null) {
            $qb->where('u.id > :cursor')
               ->setParameter('cursor', $cursor->value());
        }

        /** @var list<UserEntity> $entities */
        $entities = $qb->getQuery()->getResult();

        return array_map($this->mapper->toDomain(...), $entities);
    }

    public function delete(User $user): void
    {
        $entity = $this->em->find(UserEntity::class, $user->id()->value());

        if ($entity !== null) {
            $this->em->remove($entity);
            $this->em->flush();
        }
    }
}
