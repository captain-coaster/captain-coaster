<?php

declare(strict_types=1);
/*
 * This document has been generated with
 * https://mlocati.github.io/php-cs-fixer-configurator/#version:3.37.1|configurator
 * you can change this configuration by importing this file.
 */
$config = new PhpCsFixer\Config();

return $config
    ->setRiskyAllowed(true)
    ->setRules([
        '@Symfony:risky' => true,
        '@PHP82Migration' => true,
        '@PSR12:risky' => true,
        '@Symfony' => true,
    ])
    ->setFinder(PhpCsFixer\Finder::create()
        ->in(__DIR__)
        // ->exclude([
        //     'folder-to-exclude',
        // ])
        // ->append([
        //     'file-to-include',
        // ])
    );
