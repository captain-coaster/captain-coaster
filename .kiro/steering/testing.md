---
inclusion: fileMatch
fileMatchPattern: ['tests/**/*.php', 'src/**/*.php']
---

# Testing Guidelines

## CRITICAL: No Database Tests

**IMPORTANT**: Tests must NOT require database connections:

- **Unit tests only**: Test business logic in isolation
- **Property tests**: Use Eris for invariant testing
- **Mock all database dependencies**: Use mocks for repositories and EntityManager
- **No integration tests**: Database integration tests are not supported in this environment

## MANDATORY: Test Creation Rules

When creating or modifying code, you MUST:

1. **Always create tests** for new services, repositories, and business logic
2. **Use existing test patterns** - follow the 3-file structure for services
3. **Mock all external dependencies** including database connections
4. **Run quality checks** after test creation:
    ```bash
    vendor/bin/phpunit
    composer phpstan
    vendor/bin/php-cs-fixer fix
    ```

## Test File Structure

### Services (3-file pattern)

```
tests/Service/
├── {ServiceName}Test.php              # Core functionality
├── {ServiceName}PropertyTest.php      # Property-based tests
├── {ServiceName}ErrorHandlingTest.php # Error scenarios
```

### Repositories & Other Classes

```
tests/Repository/{EntityName}RepositoryTest.php
tests/Controller/{ControllerName}Test.php
```

## Test Types & Naming

- **Unit Tests**: `{ClassName}Test.php` - Test business logic, edge cases
- **Property Tests**: `{ClassName}PropertyTest.php` - Test invariants with Eris
- **Error Tests**: `{ClassName}ErrorHandlingTest.php` - Test exceptions, logging

## Property-Based Testing (Eris)

**ALWAYS use `$this->limitTo(3)`** for performance:

```php
public function testPropertyExample(): void
{
    $this->limitTo(3); // REQUIRED - limits iterations

    $this->forAll(Generator\int())
        ->then(function (int $value): void {
            // Test invariant here
        });
}
```

**Use for**: Data transformations, mathematical properties, input validation
**Avoid for**: CRUD operations, UI interactions, external APIs

## Mock Strategy

- **ALWAYS mock database dependencies**: EntityManager, repositories, connections
- **Mock external dependencies**: HTTP clients, file systems, AWS services
- **Keep mocks simple**: Don't over-specify behavior
- **Use real objects** for value objects and simple entities
- **No database connections**: Tests must work without MariaDB/MySQL running

## Performance Requirements

- **Unit tests**: Must run in under 1 second each
- **Property tests**: Limited to 3 iterations maximum
- **Total test suite**: Should complete in under 30 seconds

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

## Commands Reference

```bash
# Run all tests
vendor/bin/phpunit

# Run specific test file
vendor/bin/phpunit tests/Service/CoasterSummaryServiceTest.php

# Run tests with coverage
vendor/bin/phpunit --coverage-html coverage/

# Run specific test method
vendor/bin/phpunit --filter testMethodName
```
