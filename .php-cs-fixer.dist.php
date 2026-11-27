<?php

$finder = PhpCsFixer\Finder::create()
    ->in([
        __DIR__ . '/config',
        __DIR__ . '/src',
        __DIR__ . '/integrations/laravel/src',
        __DIR__ . '/tests',
        __DIR__ . '/tools',
    ])
    ->exclude('vendor')
    ->ignoreDotFiles(true)
    ->ignoreVCS(true);

return (new PhpCsFixer\Config())
    ->registerCustomFixers(new ErickSkrauch\PhpCsFixer\Fixers())
    ->setRules([
        '@PSR12' => true,
        'ErickSkrauch/align_multiline_parameters' => [
            'variables' => false,
            'defaults' => false,
        ],
        'ErickSkrauch/line_break_after_statements' => true,
        'binary_operator_spaces' => [
            'default' => 'single_space',
            'operators' => [
                '=' => 'align_single_space_minimal',
                '=>' => 'align_single_space_minimal',
            ],
        ],
        'method_argument_space' => [
            'on_multiline' => 'ensure_fully_multiline',
        ],
        'blank_line_before_statement' => [
            'statements' => ['if', 'foreach'],
        ],
        'phpdoc_align' => [
            'align' => 'vertical',
        ],
        'fully_qualified_strict_types' => [
            'import_symbols' => true,
        ],
        'global_namespace_import' => [
            'import_classes' => true,
        ],
        'ordered_class_elements' => true,
        'ordered_imports' => [
            'sort_algorithm' => 'alpha',
            'imports_order' => ['class', 'function', 'const'],
        ],
    ])
    ->setRiskyAllowed(false)
    ->setUsingCache(false)
    ->setFinder($finder);
