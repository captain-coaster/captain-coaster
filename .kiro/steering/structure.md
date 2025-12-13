---
inclusion: always
---

# Project Structure & Naming Conventions

## Directory Layout

### Backend Structure

- **src/Controller/**: HTTP request handlers ONLY
- **src/Service/**: ALL business logic and calculations
- **src/Repository/**: Custom database queries using Doctrine DQL/QueryBuilder
- **src/Entity/**: Doctrine ORM entities with relationships
- **src/Security/**: Authentication, authorization, voters
- **src/Form/**: Symfony form types for validation
- **src/EventListener/**: Doctrine lifecycle and application events

### Frontend Structure

- **assets/controllers/**: Stimulus controllers
- **assets/js/**: Vanilla JavaScript utilities
- **assets/styles/**: LESS/CSS files organized by component
- **templates/**: Twig templates organized by feature/controller

### Configuration

- **config/packages/**: Service configuration files
- **translations/**: Multi-language files (EN, FR, ES, DE)
- **public/build/**: Compiled assets (managed by Webpack Encore)

## Naming Conventions

### PHP Classes

- **Controllers**: `{Feature}Controller` (e.g., `CoasterController`)
- **Services**: `{Purpose}Service` (e.g., `RatingService`)
- **Repositories**: `{Entity}Repository` (e.g., `CoasterRepository`)
- **Entities**: Singular nouns (e.g., `Coaster`, `User`)
- **Voters**: `{Entity}Voter` (e.g., `CoasterVoter`)
- **Form Types**: `{Purpose}Type` (e.g., `CoasterSearchType`)

### Frontend Assets

- **Stimulus controllers**: kebab-case (e.g., `rating-controller.js`)
- **CSS/LESS files**: kebab-case with component organization
- **JavaScript modules**: camelCase functions, kebab-case files

### Templates

- **Directory structure**: Match controller structure (`templates/Coaster/`)
- **File naming**: snake_case (e.g., `show.html.twig`)
- **Partial templates**: Prefix with underscore (e.g., `_rating_widget.html.twig`)

## Translation Domains

- `messages`: General UI text
- `database`: Entity-related text
- `validators`: Form validation messages
- `security`: Authentication/authorization text
