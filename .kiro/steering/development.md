---
inclusion: always
---

# Development Standards, Tech & Architecture

## Technology Stack & Environment

### Core Stack

- **Symfony 7.x** with **PHP 8.4**
- **Doctrine ORM 3.3** - Use QueryBuilder for complex queries
- **MariaDB 10.11** as database
- **Redis** for caching and sessions
- **API Platform 4.0** - ONLY for external (users) API routes (`/api/*`)
- **AWS SDK** - S3 for images, SES for email
- **Google OAuth2** - Primary authentication, secondary is passwordless login using email
- **EasyAdmin 4** - Admin interface at `/team`
- **Webpack Encore** build system
- **Stimulus** controllers
- **Limitless HTML Template** using their own CSS, based on Bootstrap 3
- **Bootstrap 3.4.X** mainly for CSS, few JS components used
- **Twig** templating
- **Heroicons using Symfony UX Icons**

### Development Environment

- **Server**: https://localhost:8000 (to be run as background command)
- **Asset compilation**: `npm run dev-server` (to be run as background command)
- **Database**: MariaDB with Doctrine migrations

### Code Quality Tools

- **PHPStan** - Static analysis
- **PHP CS Fixer** - PSR-12 compliance
- **PHPUnit** - Testing framework

## Core Development Principles

- **KISS**: Keep It Simple - choose the simplest solution that works
- **Readability First**: Code is read more than written
- **Minimal Code**: Write only what's absolutely necessary
- **Mobile-First**: 75% of users are mobile - prioritize mobile experience
- **25% desktop users** - Desktop is secondary but must remain functional
- **Stimulus-first**: Use Stimulus controllers over jQuery
- **CSS separation**: NO CSS styles in JavaScript or Twig files

## Critical Requirements

- **Strict typing**: ALL PHP files MUST start with `declare(strict_types=1);`
- **Code Quality**: PHPStan, php-cs-fixer and phpunit MUST PASS (see commands below)
- **Type hints**: Use return types and parameter types everywhere
- **Translations**: All user-facing text MUST be translated in the 4 supported languages (EN, FR, ES, DE)
- **Domain-specific translations per feature** - Use appropriate translation domains (messages, database, etc.)
- **Locale-prefixed routing**: All routes already include GLOBAL route prefix (`/{_locale}/...`)

## Architecture Separation

Maintain strict separation of concerns:

### Controllers (`src/Controller/`)

- Handle HTTP requests/responses ONLY
- Validate input using Symfony forms
- Call services for business logic
- **NEVER** contain business logic or database queries

### Services (`src/Service/`)

- Contain ALL business logic and application rules
- Orchestrate operations across entities
- Use repositories for data access
- Handle external API calls

### Repositories (`src/Repository/`)

- Handle database queries using Doctrine DQL/QueryBuilder
- Provide data retrieval methods for services
- **NEVER** contain business logic

## Testing Guidelines

- **Unit tests only**: Test business logic in isolation
- **Property tests**: Use Eris for invariant testing (not too much variants for performance)
- **Mock all database dependencies**: Use mocks for repositories and EntityManager
- **No integration tests**: Database integration tests are not supported for now
- **Total test suite**: Should complete in under 30 seconds

## Test Types & Naming

- **Unit Tests**: `{ClassName}Test.php` - Test business logic, edge cases
- **Property Tests**: `{ClassName}PropertyTest.php` - Test invariants with Eris

## Test Quality Standards

### Coverage Expectations

- **Services**: High coverage of business logic (80%+)
- **Controllers**: Test request/response handling only
- **Repositories**: Test custom queries and complex logic

### Code Quality

- **Clear test names**: Describe what is being tested
- **Arrange-Act-Assert**: Structure tests clearly
- **One assertion per concept**: Keep tests focused
- **Use data providers**: For testing multiple scenarios

## CRITICAL: Dev Environment commands

**THERE IS ONLY ONE COMMAND TO RUN TESTS:**

```bash
vendor/bin/phpunit
```

### Optional Variations (but still just phpunit):

```bash
# Run specific test file
vendor/bin/phpunit tests/Service/CoasterSummaryServiceTest.php

# Run specific test method
vendor/bin/phpunit --filter testMethodName

# Run tests with coverage (optional)
vendor/bin/phpunit --coverage-html coverage/
```

**PLEASE RUN NPM AND SYMFONY WEB SERVER AS BACKGROUND COMMANDS TO CHECK LOGS**

```bash
npm run dev-server
symfony server:start --listen-ip 0.0.0.0
```
