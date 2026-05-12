<?php

declare(strict_types=1);

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

$finder = Finder::create()
    ->in([__DIR__ . '/src', __DIR__ . '/tests'])
    ->name('*.php')
    ->notName('*.blade.php')
    ->ignoreDotFiles(true)
    ->ignoreVCS(true);

return (new Config())
    ->setRiskyAllowed(true)
    ->setRules([
        // ─── PSR-12 base ──────────────────────────────────────────────────
        '@PSR12'                         => true,
        '@Symfony'                       => true,

        // ─── PHP 8.4 features ─────────────────────────────────────────────
        'declare_strict_types'           => true,
        'strict_param'                   => true,
        'strict_comparison'              => true,

        // ─── Imports ──────────────────────────────────────────────────────
        'ordered_imports'                => ['sort_algorithm' => 'alpha'],
        'no_unused_imports'              => true,
        'global_namespace_import'        => [
            'import_classes'   => false,
            'import_functions' => false,
            'import_constants' => false,
        ],

        // ─── Type declarations ────────────────────────────────────────────
        'void_return'                    => true,
        'return_type_declaration'        => ['space_before' => 'none'],
        'nullable_type_declaration_for_default_null_value' => true,

        // ─── Arrays ───────────────────────────────────────────────────────
        'array_syntax'                   => ['syntax' => 'short'],
        'trailing_comma_in_multiline'    => ['elements' => ['arrays', 'arguments', 'parameters']],
        'no_multiline_whitespace_around_double_arrow' => true,

        // ─── Strings ──────────────────────────────────────────────────────
        'single_quote'                   => true,

        // ─── Control structures ───────────────────────────────────────────
        'yoda_style'                     => false,
        'no_superfluous_elseif'          => true,
        'no_useless_else'                => true,

        // ─── PHPDoc ───────────────────────────────────────────────────────
        'phpdoc_order'                   => true,
        'phpdoc_var_without_name'        => true,
        'phpdoc_align'                   => ['align' => 'vertical'],

        // ─── Final / readonly ─────────────────────────────────────────────
        'final_internal_class'           => false,  // We control final explicitly.
        'self_accessor'                  => true,
    ])
    ->setFinder($finder);
