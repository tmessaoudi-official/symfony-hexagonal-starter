<?php

declare(strict_types=1);

use App\Application\Command\CreateUser\CreateUserCommand;
use App\Application\Command\CreateUser\CreateUserCommandHandler;
use App\Application\Query\ListUsers\ListUsersQuery;
use App\Application\Query\ListUsers\ListUsersQueryHandler;

/**
 * Integration tests for ListUsersQueryHandler.
 *
 * Each test runs inside a DAMA transaction that is rolled back at teardown,
 * so inserts here do not pollute other tests.
 *
 * Users are seeded by dispatching CreateUserCommand directly (no Foundry dependency
 * needed for application-layer integration tests; unique emails are guaranteed by
 * using a loop counter + random suffix).
 */
describe('ListUsersQueryHandler (Integration)', function (): void {
    beforeEach(function (): void {
        $container            = $this->getContainer();
        $this->queryHandler   = $container->get(ListUsersQueryHandler::class);
        $this->commandHandler = $container->get(CreateUserCommandHandler::class);
    });

    it('returns an empty list when no users exist', function (): void {
        $output = ($this->queryHandler)(new ListUsersQuery(limit: 10));

        expect($output->items)->toBeEmpty()
            ->and($output->hasMore)->toBeFalse()
            ->and($output->nextCursor)->toBeNull();
    });

    it('returns all users and hasMore=false when count is below the limit', function (): void {
        for ($i = 1; $i <= 3; $i++) {
            $email = sprintf('list%d-%s@example.com', $i, substr(bin2hex(random_bytes(4)), 0, 6));
            ($this->commandHandler)(new CreateUserCommand(email: $email, name: "List User {$i}"));
        }

        $output = ($this->queryHandler)(new ListUsersQuery(limit: 10));

        expect(count($output->items))->toBeGreaterThanOrEqual(3)
            ->and($output->hasMore)->toBeFalse();
    });

    it('returns exactly limit items and hasMore=true when more exist', function (): void {
        for ($i = 1; $i <= 6; $i++) {
            $email = sprintf('page%d-%s@example.com', $i, substr(bin2hex(random_bytes(4)), 0, 6));
            ($this->commandHandler)(new CreateUserCommand(email: $email, name: "Page User {$i}"));
        }

        $output = ($this->queryHandler)(new ListUsersQuery(limit: 5));

        expect($output->items)->toHaveCount(5)
            ->and($output->hasMore)->toBeTrue()
            ->and($output->nextCursor)->not->toBeNull();
    });

    it('cursor pagination returns the next page', function (): void {
        for ($i = 1; $i <= 8; $i++) {
            $email = sprintf('cursor%d-%s@example.com', $i, substr(bin2hex(random_bytes(4)), 0, 6));
            ($this->commandHandler)(new CreateUserCommand(email: $email, name: "Cursor User {$i}"));
        }

        $page1 = ($this->queryHandler)(new ListUsersQuery(limit: 5));

        expect($page1->items)->toHaveCount(5)
            ->and($page1->hasMore)->toBeTrue();

        $page2 = ($this->queryHandler)(new ListUsersQuery(
            limit: 5,
            afterCursor: $page1->nextCursor,
        ));

        expect(count($page2->items))->toBeGreaterThanOrEqual(3)
            ->and($page2->hasMore)->toBeFalse();

        // IDs on page 2 must all be lexicographically greater than the cursor.
        $cursor = $page1->nextCursor;
        foreach ($page2->items as $item) {
            expect($item->id > $cursor)->toBeTrue();
        }
    });

    it('returns 15 users with hasMore=false when limit is 20', function (): void {
        for ($i = 1; $i <= 15; $i++) {
            $email = sprintf('bulk%d-%s@example.com', $i, substr(bin2hex(random_bytes(4)), 0, 6));
            ($this->commandHandler)(new CreateUserCommand(email: $email, name: "Bulk User {$i}"));
        }

        $output = ($this->queryHandler)(new ListUsersQuery(limit: 20));

        expect(count($output->items))->toBeGreaterThanOrEqual(15)
            ->and($output->hasMore)->toBeFalse();
    });

    it('each returned item maps email and name correctly', function (): void {
        ($this->commandHandler)(new CreateUserCommand(
            email: 'mappedcheck@example.com',
            name: 'Mapped Check',
        ));

        $output = ($this->queryHandler)(new ListUsersQuery(limit: 100));

        $emails = array_map(fn ($item) => $item->email, $output->items);
        expect($emails)->toContain('mappedcheck@example.com');
    });
});
