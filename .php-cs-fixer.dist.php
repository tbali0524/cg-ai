<?php

/**
 * php-cs-fixer configuration file.
 *
 * minimum version: ^3.89
 *
 * @see https://cs.symfony.com/doc/config.html
 */

declare(strict_types=1);

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__)
    ->name('*.php')
    ->ignoreVCSIgnored(true)
    ->exclude('.git/')
    ->exclude('.tests/')
    ->exclude('.tools/')
    ->exclude('vendor/')
    ->exclude('php/')   // do not check input files
;

return (new PhpCsFixer\Config())
    ->setRules([                            // rulesets: https://cs.symfony.com/doc/ruleSets/index.html
        '@PHP8x5Migration' => true,
        '@PHP8x2Migration:risky' => true,    // this also needs: ->setRiskyAllowed(true)
        '@PhpCsFixer' => true,              // includes @Symfony, @PER-CS3.0, @PSR12, @PSR2, @PSR1
        '@PhpCsFixer:risky' => true,        // includes @Symfony:risky, @PER-CS3.0:risky, @PSR12:risky

        // override
        'types_spaces' => ['space' => 'single'],

        // override some @Symfony rules
        'binary_operator_spaces' => ['operators' => ['=' => null, '=>' => null]],
        'blank_line_before_statement' => false,
        'concat_space' => ['spacing' => 'one'],
        'phpdoc_to_comment' => false,
        'trim_array_spaces' => false,
        'yoda_style' => false,

        // override some @PhpCsFixer rules
        'explicit_string_variable' => false,
        'ordered_class_elements' => false,
        'single_line_empty_body' => false,

        // override some @Symfony:risky rules
        'is_null' => false,
        'logical_operators' => false,
        'modernize_types_casting' => false,
        'native_constant_invocation' => false,
        'native_function_invocation' => false,
        'no_trailing_whitespace_in_string' => false,
        'psr_autoloading' => false,         // it would rename classes
        'string_length_to_empty' => false,

        // override some @PhpCsFixer:risky rules
        'comment_to_phpdoc' => false,
        'strict_comparison' => false,

        // override
        'new_expression_parentheses' => false,
    ])
    ->setRiskyAllowed(true)
    ->setCacheFile(__DIR__ . '/.tools/.php-cs-fixer.cache')
    ->setIndent("    ")
    ->setLineEnding("\n")
    ->setParallelConfig(PhpCsFixer\Runner\Parallel\ParallelConfigFactory::detect())
    ->setUnsupportedPhpVersionAllowed(true)
    ->setFinder($finder)
;
