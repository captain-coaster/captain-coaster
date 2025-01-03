<?php

declare(strict_types=1);

use ApiPlatform\Symfony\Bundle\ApiPlatformBundle;
use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Doctrine\Bundle\MigrationsBundle\DoctrineMigrationsBundle;
use EasyCorp\Bundle\EasyAdminBundle\EasyAdminBundle;
use EWZ\Bundle\RecaptchaBundle\EWZRecaptchaBundle;
use FOS\JsRoutingBundle\FOSJsRoutingBundle;
use Knp\Bundle\PaginatorBundle\KnpPaginatorBundle;
use Knp\Bundle\TimeBundle\KnpTimeBundle;
use KnpU\OAuth2ClientBundle\KnpUOAuth2ClientBundle;
use Oneup\FlysystemBundle\OneupFlysystemBundle;
use Stof\DoctrineExtensionsBundle\StofDoctrineExtensionsBundle;
use Symfony\Bundle\DebugBundle\DebugBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\MakerBundle\MakerBundle;
use Symfony\Bundle\MonologBundle\MonologBundle;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Bundle\WebProfilerBundle\WebProfilerBundle;
use Symfony\UX\TwigComponent\TwigComponentBundle;
use Symfony\WebpackEncoreBundle\WebpackEncoreBundle;
use SymfonyCasts\Bundle\VerifyEmail\SymfonyCastsVerifyEmailBundle;
use Twig\Extra\TwigExtraBundle\TwigExtraBundle;

return [
    FrameworkBundle::class => ['all' => true],
    DoctrineMigrationsBundle::class => ['all' => true],
    SecurityBundle::class => ['all' => true],
    TwigBundle::class => ['all' => true],
    MakerBundle::class => ['dev' => true],
    DoctrineBundle::class => ['all' => true],
    EasyAdminBundle::class => ['all' => true],
    FOSJsRoutingBundle::class => ['all' => true],
    KnpPaginatorBundle::class => ['all' => true],
    KnpTimeBundle::class => ['all' => true],
    StofDoctrineExtensionsBundle::class => ['all' => true],
    MonologBundle::class => ['all' => true],
    WebpackEncoreBundle::class => ['all' => true],
    EWZRecaptchaBundle::class => ['all' => true],
    WebProfilerBundle::class => ['dev' => true, 'test' => true],
    OneupFlysystemBundle::class => ['all' => true],
    ApiPlatformBundle::class => ['all' => true],
    TwigExtraBundle::class => ['all' => true],
    DebugBundle::class => ['dev' => true],
    KnpUOAuth2ClientBundle::class => ['all' => true],
    SymfonyCastsVerifyEmailBundle::class => ['all' => true],
    TwigComponentBundle::class => ['all' => true],
];
