<?php

declare(strict_types=1);

$finder = PhpCsFixer\Finder::create()
    ->in([
        __DIR__.'/src',
        __DIR__.'/tests',
    ])
    ->append([__FILE__]);

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@Symfony' => true,
        '@Symfony:risky' => true,
        '@PHPUnit84Migration:risky' => true,
        'array_syntax' => ['syntax' => 'short'],
        'declare_strict_types' => true,
        'fully_qualified_strict_types' => true,
        'native_function_invocation' => ['scope' => 'all'],
        'linebreak_after_opening_tag' => true,
        // NOTE: "mb_str_functions" must stay disabled: this library works on raw bytes,
        // and multibyte functions silently corrupt binary and UTF-8 payloads.
        'mb_str_functions' => false,
        'no_superfluous_phpdoc_tags' => ['allow_mixed' => true],
        'ordered_imports' => ['sort_algorithm' => 'length'],
        'phpdoc_order' => true,
        'protected_to_private' => false,
        'strict_param' => true,
        'yoda_style' => [
            'always_move_variable' => false,
            'equal' => false,
            'identical' => false,
        ],
        // PHP 7.2 compatibility: no PHP 8 functions, no trailing commas in arguments/parameters
        'modernize_strpos' => false,
        'trailing_comma_in_multiline' => ['elements' => ['arrays']],
    ])
    ->setFinder($finder);
