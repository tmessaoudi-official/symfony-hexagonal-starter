<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Application\Command\CreateUser\CreateUserCommand;
use App\Application\Command\CreateUser\CreateUserCommandHandler;
use App\Domain\Model\Email;
use App\Domain\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * Functional tests for the User HTTP API.
 *
 * Uses Symfony's WebTestCase with KernelBrowser — no real HTTP server needed.
 * DAMA DoctrineTestBundle wraps each test in a rolled-back transaction.
 *
 * Content negotiation: API Platform 4 returns application/ld+json by default.
 * We request application/json (Accept header) and assert on that.
 */
final class UserApiTest extends WebTestCase
{
    private \Symfony\Bundle\FrameworkBundle\KernelBrowser $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = static::createClient();
        $this->client->disableReboot();
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    /**
     * @param array<string, mixed> $data
     */
    private function postUser(array $data): Response
    {
        $this->client->request(
            method: 'POST',
            uri: '/api/users',
            content: json_encode($data, JSON_THROW_ON_ERROR),
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT'  => 'application/json',
            ],
        );

        return $this->client->getResponse();
    }

    private function getUser(string $id): Response
    {
        $this->client->request(
            method: 'GET',
            uri: '/api/users/' . $id,
            server: ['HTTP_ACCEPT' => 'application/json'],
        );

        return $this->client->getResponse();
    }

    /**
     * @param array<string, string|int> $query
     */
    private function listUsers(array $query = []): Response
    {
        $uri = '/api/users';
        if (!empty($query)) {
            $uri .= '?' . http_build_query($query);
        }

        $this->client->request(
            method: 'GET',
            uri: $uri,
            server: ['HTTP_ACCEPT' => 'application/json'],
        );

        return $this->client->getResponse();
    }

    /**
     * Creates a user via the command handler directly (bypassing HTTP) and returns its ID.
     */
    private function seedUser(string $email, string $name): string
    {
        $container = static::getContainer();

        /** @var CreateUserCommandHandler $handler */
        $handler = $container->get(CreateUserCommandHandler::class);
        $handler(new CreateUserCommand(email: $email, name: $name));

        /** @var UserRepository $repo */
        $repo = $container->get(UserRepository::class);
        $user = $repo->findByEmail(Email::fromString($email));

        return $user->id()->value();
    }

    // ─── POST /api/users ─────────────────────────────────────────────────────

    public function testCreateUserReturns201WithCorrectBody(): void
    {
        $response = $this->postUser(['email' => 'create@example.com', 'name' => 'Create User']);

        self::assertSame(201, $response->getStatusCode());
        self::assertStringContainsString('application/json', (string) $response->headers->get('Content-Type'));

        /** @var array<string, mixed> $body */
        $body = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertArrayHasKey('id', $body);
        self::assertSame('create@example.com', $body['email']);
        self::assertSame('Create User', $body['name']);
        self::assertSame('active', $body['status']);
    }

    public function testCreateUserWithInvalidEmailReturns422(): void
    {
        $response = $this->postUser(['email' => 'not-an-email', 'name' => 'Bad Email']);

        self::assertSame(422, $response->getStatusCode());
    }

    public function testCreateUserWithMissingEmailReturns422(): void
    {
        $response = $this->postUser(['name' => 'No Email']);

        self::assertSame(422, $response->getStatusCode());
    }

    public function testCreateUserWithEmptyNameReturns422(): void
    {
        $response = $this->postUser(['email' => 'emptyname@example.com', 'name' => '']);

        self::assertSame(422, $response->getStatusCode());
    }

    public function testCreateUserWithTooShortNameReturns422(): void
    {
        $response = $this->postUser(['email' => 'shortname@example.com', 'name' => 'X']);

        self::assertSame(422, $response->getStatusCode());
    }

    public function testCreateUserWithMissingNameReturns422(): void
    {
        $response = $this->postUser(['email' => 'missingname@example.com']);

        self::assertSame(422, $response->getStatusCode());
    }

    public function testCreateDuplicateUserReturns409(): void
    {
        $this->seedUser('duplicate-api@example.com', 'First');

        $response = $this->postUser(['email' => 'duplicate-api@example.com', 'name' => 'Second']);

        self::assertSame(409, $response->getStatusCode());
    }

    // ─── GET /api/users/{id} ─────────────────────────────────────────────────

    public function testGetUserByIdReturns200(): void
    {
        $id = $this->seedUser('getbyid@example.com', 'Get By Id');

        $response = $this->getUser($id);

        self::assertSame(200, $response->getStatusCode());

        /** @var array<string, mixed> $body */
        $body = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame($id, $body['id']);
        self::assertSame('getbyid@example.com', $body['email']);
        self::assertSame('Get By Id', $body['name']);
        self::assertSame('active', $body['status']);
    }

    public function testGetNonExistentUserReturns404(): void
    {
        $response = $this->getUser('00000000-0000-0000-0000-000000000000');

        self::assertSame(404, $response->getStatusCode());
    }

    public function testGetUserWithMalformedIdReturns404(): void
    {
        $response = $this->getUser('not-a-valid-uuid');

        self::assertSame(404, $response->getStatusCode());
    }

    // ─── GET /api/users ───────────────────────────────────────────────────────

    public function testListUsersReturns200WithPaginationStructure(): void
    {
        $response = $this->listUsers();

        self::assertSame(200, $response->getStatusCode());

        /** @var array<string, mixed> $body */
        $body = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertArrayHasKey('items', $body);
        self::assertArrayHasKey('hasMore', $body);
        self::assertIsArray($body['items']);
    }

    public function testListUsersRespectsLimitParameter(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->seedUser("limitparam{$i}@example.com", "Limit User {$i}");
        }

        $response = $this->listUsers(['limit' => 3]);

        self::assertSame(200, $response->getStatusCode());

        /** @var array<string, mixed> $body */
        $body = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertCount(3, $body['items']);
        self::assertTrue($body['hasMore']);
        self::assertArrayHasKey('nextCursor', $body);
    }

    public function testListUsersWithCursorReturnsNextPage(): void
    {
        for ($i = 1; $i <= 6; $i++) {
            $this->seedUser("cursor{$i}@example.com", "Cursor User {$i}");
        }

        // First page.
        $page1Response = $this->listUsers(['limit' => 4]);
        /** @var array<string, mixed> $page1 */
        $page1 = json_decode((string) $page1Response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertCount(4, $page1['items']);
        self::assertTrue($page1['hasMore']);
        $cursor = (string) $page1['nextCursor'];

        // Second page using cursor.
        $page2Response = $this->listUsers(['limit' => 4, 'after' => $cursor]);
        /** @var array<string, mixed> $page2 */
        $page2 = json_decode((string) $page2Response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertGreaterThanOrEqual(2, count($page2['items']));

        // All page-2 IDs must be greater than the cursor (lexicographic; UUIDs are sortable here).
        foreach ($page2['items'] as $item) {
            self::assertGreaterThan($cursor, $item['id']);
        }
    }

    public function testResponseContentTypeIsJson(): void
    {
        $response = $this->listUsers();

        self::assertStringContainsString('application/json', (string) $response->headers->get('Content-Type'));
    }
}
