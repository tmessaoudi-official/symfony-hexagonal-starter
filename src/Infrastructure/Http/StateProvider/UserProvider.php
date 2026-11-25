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
use App\Domain\Model\UserId;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
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
final readonly class UserProvider implements ProviderInterface
{
    public function __construct(
        private GetUserQueryHandler $getUserHandler,
        private ListUsersQueryHandler $listUsersHandler,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): UserOutput|UserListOutput
    {
        if ($operation instanceof GetCollection) {
            return $this->provideCollection($context);
        }

        return $this->provideItem($uriVariables);
    }

    /** @param array<string, mixed> $uriVariables */
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

    /** @param array<string, mixed> $context */
    private function provideCollection(array $context): UserListOutput
    {
        $filters = $context['filters'] ?? [];
        $limit = isset($filters['limit']) ? (int) $filters['limit'] : 20;
        $after = $filters['after'] ?? '';

        // The cursor is a user id: ask the value object here, at the boundary, so a malformed one
        // (including ?after[]=x, which arrives as an array) is the client's 400 instead of a 500
        // from inside the handler.
        if (!is_string($after)) {
            throw new BadRequestHttpException('Invalid "after" cursor: expected a user id.');
        }

        $cursor = $after !== '' ? $after : null;
        if ($cursor !== null) {
            try {
                UserId::fromString($cursor);
            } catch (\InvalidArgumentException $e) {
                throw new BadRequestHttpException('Invalid "after" cursor: expected a user id.', $e);
            }
        }

        return ($this->listUsersHandler)(new ListUsersQuery(
            limit: max(1, min(100, $limit)),
            afterCursor: $cursor,
        ));
    }
}
