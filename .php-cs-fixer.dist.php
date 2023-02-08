<?php
$finder = PhpCsFixer\Finder::create()
    ->ignoreDotFiles(false)
    ->in([
        __DIR__.'/src',
        __DIR__.'/tests'
    ]);
$config = new PhpCsFixer\Config();
$config
    ->setRiskyAllowed(true)
    ->setRules([
        '@Symfony' => true,
        '@Symfony:risky' => true,
        '@PHPUnit48Migration:risky' => true,
        'array_syntax' => ['syntax' => 'short'],
        'declare_strict_types' => false,
        'fully_qualified_strict_types' => true,
        'native_function_invocation' => ['scope' => 'all'],
        'line_ending' => true,
        'linebreak_after_opening_tag' => true,
        'constant_case' => true,
        'mb_str_functions' => true,
        'no_closing_tag' => true,
        'no_trailing_whitespace' => true,
        'ordered_imports' => [
            'sort_algorithm' => 'length'
        ],
        'phpdoc_order' => true,
        'protected_to_private' => false,
        'strict_param' => true,
        'single_trait_insert_per_statement' => false,
        'yoda_style' => [
            'always_move_variable' => false,
            'equal' => false,
            'identical' => false,
        ]
    ])
    ->setFinder($finder);

return $config;