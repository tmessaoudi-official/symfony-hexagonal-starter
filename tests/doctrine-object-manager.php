<?php

declare(strict_types=1);

/**
 * Doctrine Object Manager loader for PHPStan.
 *
 * WHY this file exists:
 * phpstan/phpstan-doctrine needs an EntityManager instance to inspect entity metadata
 * (mapped columns, associations, enum types) at static analysis time. Without this,
 * it falls back to reflection-only analysis and misses ORM-specific errors such as:
 *   - Property types that don't match the column mapping
 *   - Relations that point to non-entity classes
 *   - DQL queries that reference unmapped fields
 *
 * The bootstrap creates a minimal "test" kernel, warms up just enough to get the
 * EntityManager from the DI container, and returns it. PHPStan calls this file once
 * per analysis run.
 *
 * It loads .env itself, like bin/console and tests/bootstrap.php: PHPStan does not, so without
 * this line the kernel only sees the shell environment, and a plain `vendor/bin/phpstan analyse`
 * outside the compose stack (CI, a fresh clone) stops on `Environment variable not found:
 * "DATABASE_URL"`. No connection is opened: the URL pins serverVersion.
 */

use App\Kernel;
use Symfony\Component\Dotenv\Dotenv;

require_once __DIR__.'/../vendor/autoload.php';

new Dotenv()->bootEnv(__DIR__.'/../.env');

$kernel = new Kernel('test', true);
$kernel->boot();

return $kernel->getContainer()->get('doctrine.orm.entity_manager');
