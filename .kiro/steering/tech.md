---
inclusion: always
---

# Technology Stack & Environment

## Core Stack

- **Symfony 7.x** with **PHP 8.4**
- **Doctrine ORM 3.3** - Use QueryBuilder for complex queries
- **Twig** templating
- **MariaDB 10.11** + **Redis** for caching and sessions
- **Stimulus** controllers (preferred over jQuery)
- **Webpack Encore** - Assets compiled automatically, DO NOT run `npm run build`
- **Bootstrap 5** with **LESS** styling

## Critical Integrations

- **API Platform 4.0** - ONLY for external API routes (`/api/*`)
- **AWS SDK** - S3 for images, SES for email
- **Google OAuth2** - Primary authentication
- **EasyAdmin 4** - Admin interface at `/admin`

## Development Environment

- **Server**: https://localhost:8000 (Symfony server running)
- **Asset compilation**: `npm run dev-server` (already running)
- **Database**: MariaDB with Doctrine migrations

## Code Quality Tools

- **PHPStan** - Static analysis (level 8)
- **PHP CS Fixer** - PSR-12 compliance
- **PHPUnit** - Testing framework
