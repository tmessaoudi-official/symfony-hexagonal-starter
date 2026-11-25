<?php

declare(strict_types=1);

/*
 * Behat 4 configuration (Behat 4 dropped YAML configuration and annotation-defined steps).
 *
 * The scenarios drive the Symfony kernel in-process through KernelBrowser: no browser and no
 * HTTP server, so they test the API contract exactly as the functional tests do, but in
 * business language. DAMA's extension wraps every scenario in a rolled-back transaction.
 */

use App\Kernel;
use App\Tests\Behat\ApiContext;
use App\Tests\Behat\UserContext;
use Behat\Config\Config;
use Behat\Config\Extension;
use Behat\Config\Profile;
use Behat\Config\Suite;
use DAMA\DoctrineTestBundle\Behat\ServiceContainer\DoctrineExtension;
use FriendsOfBehat\SymfonyExtension\ServiceContainer\SymfonyExtension;

return (new Config())
    ->withProfile(
        (new Profile('default'))
            ->withExtension(new Extension(SymfonyExtension::class, [
                'bootstrap' => 'tests/bootstrap.php',
                'kernel' => [
                    'class' => Kernel::class,
                    'environment' => 'test',
                    'debug' => true,
                ],
            ]))
            ->withExtension(new Extension(DoctrineExtension::class))
            ->withSuite(
                (new Suite('default'))
                    ->withPaths('features')
                    ->withContexts(ApiContext::class, UserContext::class),
            ),
    );
