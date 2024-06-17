<?php

use Rector\Symfony\Set\SymfonySetList;
use Rector\Set\ValueObject\LevelSetList;
use Rector\Config\RectorConfig;
use Rector\Doctrine\Set\DoctrineSetList;
use Rector\Renaming\Rector\FuncCall\RenameFunctionRector;

$base = __DIR__ . '/../..';

return RectorConfig::configure()
    ->withBootstrapFiles([
        $base . '/app/vendor/autoload.php',
    ])
    ->withPaths([
        $base . '/aropixel/AdminBundle',
        $base . '/aropixel/BlogBundle',
        $base . '/aropixel/MenuBundle',
        $base . '/aropixel/PageBundle',
    ])
    ->withSets([
        LevelSetList::UP_TO_PHP_82,
//        SymfonySetList::SYMFONY_60,
//        SymfonySetList::SYMFONY_CODE_QUALITY,
//        SymfonySetList::SYMFONY_CONSTRUCTOR_INJECTION
//        DoctrineSetList::ANNOTATIONS_TO_ATTRIBUTES,
//        SymfonySetList::ANNOTATIONS_TO_ATTRIBUTES,
    ])
    ->withConfiguredRule(RenameFunctionRector::class, [
        'strlen' => 'mb_strlen',
        'strpos' => 'mb_strpos',
        'strrpos' => 'mb_strrpos',
        'substr' => 'mb_substr',
        'strtolower' => 'mb_strtolower',
        'strtoupper' => 'mb_strtoupper',
        'strstr' => 'mb_strstr',
        'substr_count' => 'mb_substr_count',
        'stripos' => 'mb_stripos',
    ])
;

