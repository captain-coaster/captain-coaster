---
inclusion: always
---

# Development Standards & Quality Requirements

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
- **Vite** build system (migrating from Webpack Encore)
- **Stimulus** controllers with **Tailwind CSS v4**
- **Twig** templating with **Symfony UX Icons** (Tabler Icons)

### Code Quality Tools

- **PHPStan** - Static analysis
- **PHP CS Fixer** - PSR-12 compliance
- **PHPUnit** - Testing framework

### Development Environment Commands

- **Server**: `symfony server:start --listen-ip 0.0.0.0` (to be run as background command)
- **Asset Server**: `npm run dev` (to be run as background command)
- **Asset Build**: `npm run build`
- **PHPUnit**: `vendor/bin/phpunit`
- **PHPStan**: `vendor/bin/phpstan`
- **PHP CS Fixer**: `vendor/bin/php-cs-fixer fix`

## Core Development Principles

- **KISS**: Keep It Super Simple - choose the simplest solution that works
- **NEVER OVER ENGINEER**
- **Mobile-First**: 75% of users are mobile - prioritize mobile experience
- **25% desktop users** - Desktop is secondary but must remain functional
- **Stimulus-first**: Use Stimulus controllers over jQuery
- **CSS separation**: NO CSS styles in JavaScript or Twig files
- **Utility-first CSS**: Use Tailwind utilities directly in templates
- **JavaScript utilities**: Use shared utility modules instead of inline style manipulation

## Critical Requirements

- **Strict typing**: ALL PHP files MUST start with `declare(strict_types=1);`
- **Code Quality**: PHPStan, php-cs-fixer and phpunit MUST PASS
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

## Frontend Development Standards

### CSS Architecture

- **Tailwind CSS v4** utility-first approach
- Use utilities directly in templates, avoid `@apply` except for complex patterns

### JavaScript Architecture

- **Stimulus controllers** extending `base_controller.js`
- **Utility modules** in `assets/js/utils/` for DOM manipulation, animations, API calls
- **No inline styles** - use utility functions instead
- **Class-based animations** over inline style manipulation

### Icon System

- **Tabler Icons only** via Symfony UX Icons
- Consistent sizing: `w-3 h-3` to `w-8 h-8`
- Always include `aria-hidden="true"` for decorative icons

## Translation Domains

- `messages`: General UI text
- `database`: Entity-related text
- `validators`: Form validation messages
- `security`: Authentication/authorization text
