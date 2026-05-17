<?php

declare(strict_types=1);

namespace App\Application\Command\PromoteUser;

use App\Domain\Model\UserId;
use App\Domain\Model\UserRole;
use App\Domain\Repository\UserRepository;
use App\Domain\Service\UserPromotionPolicy;
use DateTimeImmutable;
use DomainException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
final class PromoteUserCommandHandler
{
    public function __construct(
        private readonly UserRepository      $repository,
        private readonly UserPromotionPolicy $policy,
        private readonly MessageBusInterface $bus,
    ) {}

    public function __invoke(PromoteUserCommand $command): void
    {
        $user       = $this->repository->getById(UserId::fromString($command->userId));
        $targetRole = UserRole::from($command->targetRole);
        $now        = new DateTimeImmutable();

        $result = $this->policy->check($user, $targetRole, $this->repository, $now);

        if (!$result->isEligible()) {
            throw new DomainException($result->reason() ?? 'User is not eligible for promotion.');
        }

        $user->promote($targetRole);

        $this->repository->save($user);

        foreach ($user->pullDomainEvents() as $event) {
            $this->bus->dispatch($event);
        }
    }
}
