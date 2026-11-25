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
 */

use App\Kernel;

require_once __DIR__.'/../vendor/autoload.php';

$kernel = new Kernel('test', true);
$kernel->boot();

return $kernel->getContainer()->get('doctrine.orm.entity_manager');
