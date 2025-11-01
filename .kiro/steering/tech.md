# Technology Stack

## Core Framework
- **Symfony 7.x**: Modern PHP framework with MicroKernelTrait
- **PHP 8.4**: Latest PHP version with strict typing enabled
- **Doctrine ORM 3.3**: Database abstraction and entity management
- **Twig**: Template engine for frontend rendering

## Frontend & Assets
- **Webpack Encore**: Asset compilation and management
- **Stimulus**: JavaScript framework for progressive enhancement
- **Bootstrap**: CSS framework for responsive design
- **Symfony UX Components**: Modern frontend components

## Key Libraries & Services
- **API Platform 4.0**: REST/GraphQL API framework
- **EasyAdmin 4**: Admin interface
- **AWS SDK**: Cloud storage integration (S3, SES)
- **OAuth2**: Google authentication
- **reCAPTCHA**: Spam protection
- **Redis**: Caching and session storage
- **Flysystem**: File storage abstraction

## Database & Storage
- **MariaDB 10.11**: Primary database
- **Redis**: Cache and message queue
- **AWS S3**: File storage for images

## Development Tools
- **PHP CS Fixer**: Code style enforcement (@Symfony rules)
- **PHPStan**: Static analysis with Doctrine/Symfony extensions
- **PHPUnit**: Testing framework
- **Docker**: Containerized development environment

## Common Commands

### Development
```bash
# Start development server (local)
symfony server:start

# Start with Docker
docker-compose up -d
docker-compose -f docker-compose.full.yml up --build -d

# Install dependencies
composer install
npm install
```

### Asset Management
```bash
# Development build
npm run dev
npm run watch

# Production build
npm run build
```

### Code Quality
```bash
# Run static analysis
composer phpstan
vendor/bin/phpstan

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