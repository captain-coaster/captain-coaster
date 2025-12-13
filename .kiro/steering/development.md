---
inclusion: always
---

# Development Standards & Architecture

## Core Principles

- **KISS**: Keep It Simple - choose the simplest solution that works
- **Readability First**: Code is read more than written
- **Minimal Code**: Write only what's absolutely necessary
- **Mobile-First**: 75% of users are mobile - prioritize mobile experience

## Critical Requirements

- **Strict typing**: ALL PHP files must start with `declare(strict_types=1);`
- **PSR-12 compliance**: Enforced by PHP CS Fixer
- **Type hints**: Use return types and parameter types everywhere
- **Translations**: All user-facing text must be translatable (EN, FR, ES, DE)
- **Locale-prefixed routing**: All routes include locale (`/{_locale}/...`)

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

## Code Patterns

### Service Injection

```php
public function __construct(
    private readonly SomeService $someService,
    private readonly EntityManagerInterface $entityManager,
) {}
```

### Controller Structure

```php
#[Route('/{_locale}/path', name: 'route_name')]
public function action(Request $request, SomeService $service): Response
{
    // Validate input
    // Call service
    // Return response
}
```

## Frontend Standards

- **Stimulus-first**: Use Stimulus controllers over jQuery
- **Progressive enhancement**: Ensure functionality works without JavaScript
- **Mobile-first responsive**: Bootstrap utilities and components
