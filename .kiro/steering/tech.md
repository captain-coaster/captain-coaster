# Technology Stack

## Core Framework
- **Symfony 7.x**: Modern PHP framework with MicroKernelTrait
- **PHP 8.4**: Latest PHP version with strict typing enabled
- **Doctrine ORM 3.3**: Database abstraction and entity management
- **Twig**: Template engine for frontend rendering

## Frontend & Assets
- **Webpack Encore**: Asset compilation and management
- **Stimulus**: JavaScript framework for progressive enhancement - **PREFERRED over jQuery**
- **Bootstrap**: CSS framework for responsive design
- **Symfony UX Components**: Modern frontend components

### Frontend Guidelines
- **Avoid jQuery**: Use Stimulus controllers for interactive features whenever possible
- **Separate CSS and JS**: Keep stylesheets and JavaScript in separate files
- **Progressive Enhancement**: Use Stimulus for modern, maintainable JavaScript
- **Component-based**: Organize code into reusable Stimulus controllers
- **Translation Required**: Every piece of text in templates or JS must be translated using Symfony translation mechanisms in all 4 languages (English, French, Spanish, German)

### Frontend Development Workflow
- **Start dev-server first**: Before making any frontend changes, start the dev-server with `npm run dev-server`
- **Keep dev-server running**: Leave the dev-server running throughout your development session for instant feedback

## Key Libraries & Services
- **API Platform 4.0**: REST/GraphQL API framework
- **EasyAdmin 4**: Admin interface
- **AWS SDK**: Cloud storage integration (S3, SES)
- **OAuth2**: Google authentication
- **reCAPTCHA**: Spam protection
- **Redis**: Caching and session storage
- **Flysystem**: File storage abstraction

## Routing Architecture
- **External API Routes**: All routes starting with `/api` are used to provide an API for external users with API Platform, not for the application itself
- **Internal Application APIs**: APIs for the application are served with standard Symfony Controllers (not API Platform)
- **Locale Prefixing**: All application routes are prefixed with the locale (with few exceptions)

## Database & Storage
- **MariaDB 10.11**: Primary database
- **Redis**: Cache and message queue
- **AWS S3**: File storage for images

## Development Tools
- **PHP CS Fixer**: Code style enforcement (@Symfony rules)
- **PHPStan**: Static analysis with Doctrine/Symfony extensions
- **PHPUnit**: Testing framework
- **Docker**: Containerized development environment

## Development Environment

### Local Development URL
- **Application URL**: https://localhost:8000 (when using `symfony server:start`)

## Common Commands

### Development
```bash
# Start development server (local)
symfony server:start

# Start with Docker
docker-compose up -d

# Install dependencies
composer install
npm install
```

### Asset Management (Webpack Encore)

**For Development:**
```bash
# Development server with hot reload - START THIS FIRST!
npm run dev-server
# Production build with bundle analysis
npm run build:analyze
# Clean build directory
npm run clean
```

**For production:**
```bash
# Production build (ONLY for deployment)
npm run build
```

### Code Quality
```bash
# Run static analysis
composer phpstan

# Fix code style
vendor/bin/php-cs-fixer fix

# Run tests
vendor/bin/phpunit
```

### Database
```bash
# Run migrations
php bin/console doctrine:migrations:migrate

# Create migration
php bin/console make:migration
```