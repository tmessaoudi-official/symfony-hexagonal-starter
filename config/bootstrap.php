<?php

declare(strict_types=1);

/**
 * Test bootstrap for Behat (FriendsOfBehat\SymfonyExtension).
 *
 * WHY this file exists:
 * FriendsOfBehat\SymfonyExtension requires a bootstrap file that initialises
 * the PHP environment before instantiating the Symfony Kernel. In practice this
 * means: load the Composer autoloader and ensure APP_ENV / APP_DEBUG are set.
 *
 * Symfony's own test runner (phpunit.xml.dist) handles this via its <bootstrap>
 * attribute; Behat needs its own entry point.
 */

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__) . '/vendor/autoload.php';

if (file_exists(dirname(__DIR__) . '/.env.test')) {
    (new Dotenv())->bootEnv(dirname(__DIR__) . '/.env.test');
} elseif (file_exists(dirname(__DIR__) . '/.env')) {
    (new Dotenv())->bootEnv(dirname(__DIR__) . '/.env');
}

// Force test environment regardless of .env contents.
$_SERVER['APP_ENV'] = $_ENV['APP_ENV'] = 'test';
$_SERVER['APP_DEBUG'] = $_ENV['APP_DEBUG'] = '1';
