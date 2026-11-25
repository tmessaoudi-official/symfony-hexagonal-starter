<?php

declare(strict_types=1);

namespace App\Application\Command\PromoteUser;

use App\Domain\Model\UserId;
use App\Domain\Model\UserRole;
use App\Domain\Repository\UserRepository;
use App\Domain\Service\UserPromotionPolicy;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

// bus: without it, the handler is registered on every bus (command, query and event).
#[AsMessageHandler(bus: 'command.bus')]
final readonly class PromoteUserCommandHandler
{
    public function __construct(
        private UserRepository $repository,
        private UserPromotionPolicy $policy,
        // Named $eventBus so Symfony autowires event.bus. A plain $bus gets the default bus
        // (command.bus), which rejects events that have no handler, like UserPromoted.
        private MessageBusInterface $eventBus,
    ) {
    }

    public function __invoke(PromoteUserCommand $command): void
    {
        $user = $this->repository->getById(UserId::fromString($command->userId));
        $targetRole = UserRole::from($command->targetRole);
        $now = new \DateTimeImmutable();

        $result = $this->policy->check($user, $targetRole, $this->repository, $now);

        if (!$result->isEligible()) {
            throw new \DomainException($result->reason() ?? 'User is not eligible for promotion.');
        }

        $user->promote($targetRole);

        $this->repository->save($user);

        foreach ($user->pullDomainEvents() as $event) {
            $this->eventBus->dispatch($event);
        }
    }
}
