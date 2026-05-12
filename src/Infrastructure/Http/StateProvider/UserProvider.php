<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\StateProvider;

use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Application\DTO\Output\UserListOutput;
use App\Application\DTO\Output\UserOutput;
use App\Application\Query\GetUser\GetUserQuery;
use App\Application\Query\GetUser\GetUserQueryHandler;
use App\Application\Query\ListUsers\ListUsersQuery;
use App\Application\Query\ListUsers\ListUsersQueryHandler;
use App\Domain\Exception\UserNotFoundException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * UserProvider — the read adapter for GET /api/users and GET /api/users/{id}.
 *
 * Dispatches the appropriate Query based on the operation type.
 * Single provider for both item and collection operations — kept together because
 * the routing logic is minimal. Split when it grows complex.
 *
 * @implements ProviderInterface<UserOutput|UserListOutput>
 */
final class UserProvider implements ProviderInterface
{
    public function __construct(
        private readonly GetUserQueryHandler $getUserHandler,
        private readonly ListUsersQueryHandler $listUsersHandler,
    ) {}

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): UserOutput|UserListOutput
    {
        if ($operation instanceof GetCollection) {
            return $this->provideCollection($context);
        }

        return $this->provideItem($uriVariables);
    }

    private function provideItem(array $uriVariables): UserOutput
    {
        $id = (string) ($uriVariables['id'] ?? '');

        try {
            return ($this->getUserHandler)(new GetUserQuery(userId: $id));
        } catch (UserNotFoundException $e) {
            throw new NotFoundHttpException($e->getMessage(), $e);
        } catch (\InvalidArgumentException $e) {
            throw new NotFoundHttpException('Invalid user identifier.', $e);
        }
    }

    private function provideCollection(array $context): UserListOutput
    {
        $filters = $context['filters'] ?? [];
        $limit   = isset($filters['limit']) ? (int) $filters['limit'] : 20;
        $cursor  = isset($filters['after']) && $filters['after'] !== '' ? (string) $filters['after'] : null;

        return ($this->listUsersHandler)(new ListUsersQuery(
            limit: max(1, min(100, $limit)),
            afterCursor: $cursor,
        ));
    }
}
