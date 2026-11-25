<?php

declare(strict_types=1);

use Rector\CodeQuality\Rector\Class_\InlineConstructorDefaultToPropertyRector;
use Rector\Config\RectorConfig;
use Rector\DeadCode\Rector\ClassMethod\RemoveUnusedPrivateMethodRector;
use Rector\DeadCode\Rector\Property\RemoveUnusedPrivatePropertyRector;
use Rector\TypeDeclaration\Rector\ClassMethod\AddVoidReturnTypeWhereNoReturnRector;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ])
    ->withSkip([
        __DIR__ . '/src/Infrastructure/Persistence/Migration',
    ])

    // ─── PHP language level ───────────────────────────────────────────────────
    // No arguments: the target is read from composer.json ("php": ">=8.6"), so a PHP
    // bump there is the only change needed. (LevelSetList is deprecated in Rector 2.)
    ->withPhpSets()

    // ─── Quality rules ────────────────────────────────────────────────────────
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        typeDeclarations: true,
        symfonyCodeQuality: true,
    )

    // ─── Framework upgrades ───────────────────────────────────────────────────
    // Upgrade sets chosen from the installed versions in composer.lock (Symfony 8.1,
    // Doctrine ORM 3, PHPUnit 13), so they never lag behind the dependencies.
    ->withComposerBased(doctrine: true, phpunit: true, symfony: true)

    // ─── Individual rules ─────────────────────────────────────────────────────
    ->withRules([
        InlineConstructorDefaultToPropertyRector::class,
        AddVoidReturnTypeWhereNoReturnRector::class,
        RemoveUnusedPrivateMethodRector::class,
        RemoveUnusedPrivatePropertyRector::class,
    ]);
