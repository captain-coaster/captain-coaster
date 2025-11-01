# Project Structure & Organization

## Directory Layout

### Core Application (`src/`)
- **Controller/**: HTTP request handlers, organized by feature area
  - `Admin/`: EasyAdmin controllers for backend management
  - Feature controllers: `CoasterController`, `ParkController`, `UserController`, etc.
- **Entity/**: Doctrine ORM entities representing database tables
- **Repository/**: Custom database query methods for entities
- **Service/**: Business logic and application services
- **Security/**: Authentication, authorization, and security voters
- **Form/**: Symfony form types and data transformers
- **Command/**: Console commands for batch operations and maintenance

### Frontend Assets (`assets/`)
- **js/**: JavaScript files and Stimulus controllers
- **css/**: Stylesheets organized by purpose (core, components, colors)
- **controllers/**: Stimulus controllers for interactive features

### Templates (`templates/`)
- Organized by controller/feature area
- **Includes/**: Reusable template fragments
- **bundles/**: Override vendor bundle templates
- Base templates: `base.html.twig`, `navbar.html.twig`, `footer.html.twig`

### Configuration (`config/`)
- **packages/**: Service-specific configuration files
- **routes/**: Routing configuration
- Environment-specific configs in `packages/dev/`, `packages/prod/`

## Naming Conventions

### PHP Classes
- **Controllers**: `{Feature}Controller` (e.g., `CoasterController`)
- **Entities**: Singular nouns (e.g., `Coaster`, `User`, `Park`)
- **Services**: `{Purpose}Service` (e.g., `RankingService`, `ImageManager`)
- **Repositories**: `{Entity}Repository` (e.g., `CoasterRepository`)
- **Voters**: `{Entity}Voter` for authorization (e.g., `CoasterVoter`)

### Database
- Entity properties use camelCase
- Database columns use snake_case (handled by Doctrine)
- Foreign keys follow `{entity}_id` pattern

### Templates
- Organized by controller namespace
- Use kebab-case for file names
- Partial templates prefixed with `_` (e.g., `_review_item.html.twig`)

## Architecture Patterns

### Domain Organization
- **Feature-based grouping**: Controllers, templates, and related code grouped by business feature
- **Service layer**: Business logic extracted to dedicated service classes
- **Repository pattern**: Custom queries encapsulated in repository classes
- **Voter pattern**: Authorization logic in dedicated voter classes

### Code Style
- **Strict typing**: All PHP files use `declare(strict_types=1);`
- **PSR-12 compliance**: Enforced by PHP CS Fixer with Symfony rules
- **Dependency injection**: Services autowired through Symfony's container
- **Event-driven**: Use of Doctrine listeners and Symfony event subscribers

### Multi-language Support
- Translation files in `translations/` using ICU message format
- Locale parameter and routing configuration
- Database content translated through separate translation entities

## File Organization Rules
- One class per file
- Namespace matches directory structure
- Configuration files use YAML format
- Environment variables defined in `.env` files
- Migrations stored in `migrations/` with timestamp naming