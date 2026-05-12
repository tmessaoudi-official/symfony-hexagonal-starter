<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Resource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use App\Application\DTO\Input\CreateUserInput;
use App\Application\DTO\Output\UserOutput;
use App\Infrastructure\Http\StateProcessor\CreateUserProcessor;
use App\Infrastructure\Http\StateProvider\UserProvider;

/**
 * UserResource — API Platform 4 resource definition.
 *
 * WHY a dedicated Resource class separate from the domain entity?
 * - API Platform resources define HTTP contracts; Domain entities define business logic.
 * - Keeping them separate means the API contract can evolve independently of the domain.
 * - The resource class here is essentially a configuration stub — it carries no data.
 *
 * Operations:
 * - POST   /api/users         → CreateUserProcessor (write via CQRS Command)
 * - GET    /api/users/{id}    → UserProvider (read via CQRS Query)
 * - GET    /api/users         → UserProvider (read collection via CQRS Query)
 *
 * The 'output' class (UserOutput) is what API Platform serialises.
 * The 'input' class (CreateUserInput) is what API Platform deserialises and validates.
 */
#[ApiResource(
    shortName: 'User',
    operations: [
        new Post(
            uriTemplate: '/users',
            input: CreateUserInput::class,
            output: UserOutput::class,
            processor: CreateUserProcessor::class,
            status: 201,
        ),
        new Get(
            uriTemplate: '/users/{id}',
            output: UserOutput::class,
            provider: UserProvider::class,
        ),
        new GetCollection(
            uriTemplate: '/users',
            output: UserOutput::class,
            provider: UserProvider::class,
        ),
    ],
    normalizationContext: ['groups' => ['user:read']],
    denormalizationContext: ['groups' => ['user:write']],
)]
final class UserResource
{
    // This class is intentionally empty — it is a metadata carrier only.
    // API Platform reads the #[ApiResource] attribute; no properties or methods are needed.
}
