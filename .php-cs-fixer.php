<?php

ini_set('memory_limit', -1);

$finder = PhpCsFixer\Finder::create()
    ->ignoreVCSIgnored(true)
    ->ignoreDotFiles(false)
    ->in(__DIR__ . '/aropixel/AdminBundle')
//    ->in(__DIR__ . '/aropixel/BlogBundle')
//    ->in(__DIR__ . '/aropixel/MenuBundle')
//    ->in(__DIR__ . '/aropixel/PageBundle')
    ->exclude('Migrations')
    ->append([
        __FILE__,
    ])
;

return (new PhpCsFixer\Config())
    ->registerCustomFixers(new PhpCsFixerCustomFixers\Fixers())
    ->setRiskyAllowed(true)
    ->setRules([
        '@PHP81Migration' => true,
        '@PhpCsFixer' => true,
        '@Symfony' => true,
        '@Symfony:risky' => true,
        'php_unit_internal_class' => false, // From @PhpCsFixer but we don't want it
        'php_unit_test_class_requires_covers' => false, // From @PhpCsFixer but we don't want it
        'phpdoc_add_missing_param_annotation' => false, // From @PhpCsFixer but we don't want it
        'concat_space' => ['spacing' => 'one'],
        'ordered_class_elements' => false, // should be true, will do it later
        'blank_line_before_statement' => true, // Symfony(PSR12) override the default value, but we don't want
        'header_comment' => [
            'header' => '',
        ],
        PhpCsFixerCustomFixers\Fixer\MultilinePromotedPropertiesFixer::name() => true,
    ])
    ->setFinder($finder)
;
