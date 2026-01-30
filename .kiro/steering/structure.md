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
    - `base_controller.js` - Shared functionality for all controllers
    - `{feature}_controller.js` - Feature-specific controllers (kebab-case)
- **assets/js/**: Vanilla JavaScript utilities
    - `utils/dom.js` - DOM manipulation utilities (show/hide, classes, scroll lock)
    - `utils/animation.js` - Class-based animation utilities
    - `utils/api.js` - Fetch helpers with CSRF support
    - `utils/validation.js` - Form validation helpers
- **assets/css/**: Tailwind CSS v4 architecture
    - `app.css` - Main entry point with @theme and optional @source directives
    - `base/typography.css` - Font loading and base text styles
    - `utilities/animations.css` - Custom @utility directives for animations
- **assets/icons/**: Icon assets
    - `tabler/` - Tabler icon set (consolidated from multiple sets)
- **templates/**: Twig templates organized by feature/controller
    - `components/` - Reusable UI components (card, empty_state, loading)
    - `macros/ui.html.twig` - UI macro library (button, badge, starRating)
    - `form/tailwind_theme.html.twig` - Symfony form theme with Tailwind utilities

### Configuration

- **config/packages/**: Service configuration files
- **translations/**: Multi-language files (EN, FR, ES, DE)
- **public/build/**: Compiled assets (managed by Vite)

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
- **CSS files**: kebab-case (e.g., `typography.css`, `animations.css`)
- **JavaScript modules**: camelCase functions, kebab-case files

### Templates

**Directory structure**:

- `templates/layouts/` - Base layout templates (base.html.twig, base_login.html.twig)
- `templates/partials/` - Shared partial templates (navbar, sidebar, footer, etc.)
- `templates/macros/` - Macro definition files
    - `ui.html.twig` - UI macro library (button, badge, starRating macros)
    - `_macros_helpers.html.twig` - Legacy helper macros (being phased out)
- `templates/components/` - Reusable UI components
    - `card.html.twig` - Card layout component
    - `empty_state.html.twig` - Empty state pattern component
    - `loading.html.twig` - Loading spinner component
- `templates/form/` - Form theming
    - `tailwind_theme.html.twig` - Symfony form theme with Tailwind utilities
- `templates/email/` - Email templates
- `templates/{Feature}/` - Feature-specific templates (PascalCase matching controller)

**Naming conventions**:

- **Feature directories**: PascalCase matching controller name without "Controller" suffix
    - `CoasterController` → `templates/Coaster/` (not `coaster/`)
    - `RankingController` → `templates/Ranking/` (not `ranking/`)
- **File naming**: snake_case (e.g., `show.html.twig`, `list_ratings.html.twig`)
- **Partial templates**: Prefix with underscore (e.g., `_rating_widget.html.twig`)
- **Macro files**: Prefix with `_macros_` (e.g., `_macros_helpers.html.twig`)
- **Layout files**: Prefix with `base_` (e.g., `base.html.twig`, `base_login.html.twig`)

**Include syntax**:

- Use `{% include %}` tag, not `{{ include() }}` function
- Use `only` keyword for partials to make dependencies explicit
- Always specify translation domains explicitly

**Path examples**:

- Layouts: `{% extends 'layouts/base.html.twig' %}`
- Partials: `{% include 'partials/_navbar.html.twig' %}`
- Components: `{% include 'components/card.html.twig' with { title: 'Card Title' } %}`
- Macros: `{% import 'macros/ui.html.twig' as ui %}` then `{{ ui.button('Save', 'primary') }}`
- Legacy macros: `{% import 'macros/_macros_helpers.html.twig' as helper %}`
- Email: `->htmlTemplate('email/notification.html.twig')`
