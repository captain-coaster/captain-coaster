<?php

declare(strict_types=1);
use PhpCsFixer\Config;
use PhpCsFixer\Finder;
use PhpCsFixer\Runner\Parallel\ParallelConfigFactory;

/*
 * This document has been generated with
 * https://mlocati.github.io/php-cs-fixer-configurator/#version:3.37.1|configurator
 * you can change this configuration by importing this file.
 */
$config = new Config();

return $config
    ->setParallelConfig(ParallelConfigFactory::detect())
    ->setRiskyAllowed(true)
    ->setRules([
        '@Symfony' => true,
        '@Symfony:risky' => true,
        '@PHP80Migration:risky' => true,
        '@PHP82Migration' => true,
        'phpdoc_line_span' => ['const' => 'single', 'method' => 'single', 'property' => 'single'],
        'method_argument_space' => ['on_multiline' => 'ensure_fully_multiline'],
        'fully_qualified_strict_types' => ['import_symbols' => true],
    ])
    ->setFinder(
        Finder::create()
        ->in(__DIR__)
        ->exclude([
            'var',
            'vendor',
            'tests',
            'config',
            'public',
        ])
        // ->append([
        //     'file-to-include',
        // ])
    );
