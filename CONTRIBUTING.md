# Contributing to laravel-setanjo

Thank you for considering contributing to laravel-setanjo! This document provides guidelines and information for contributors.

## Code of Conduct

This project adheres to a code of conduct. By participating, you are expected to uphold this code. Please report unacceptable behavior to the project maintainers.

## How Can I Contribute?

### Reporting Bugs

Before creating bug reports, please check the existing issues to avoid duplicates. When creating a bug report, include:

- **Clear title and description**
- **Steps to reproduce** the issue
- **Expected vs actual behavior**
- **Environment details** (PHP version, Laravel version, package version)
- **Code samples** that demonstrate the issue

### Suggesting Enhancements

Enhancement suggestions are welcome! Please provide:

- **Clear title and detailed description**
- **Use case** explaining why this enhancement would be useful
- **Possible implementation** details if you have ideas

### Pull Requests

1. **Fork** the repository
2. **Create a feature branch** from `main`
3. **Make your changes** following our coding standards
4. **Add tests** for new functionality
5. **Ensure all tests pass**
6. **Update documentation** if needed
7. **Submit a pull request**

## Development Setup

```bash
# Clone your fork
git clone https://github.com/ahs12/laravel-setanjo.git
cd laravel-setanjo

# Install dependencies
composer install

# Run tests
composer test

# Run code style fixes
composer format
```

## Coding Standards

- Follow **PSR-12** coding standards
- Use **meaningful variable and method names**
- Add **type hints** where possible
- Write **comprehensive tests** for new features
- Keep **backward compatibility** in mind

### Code Style

We use Pint Fixer to maintain consistent code style:

```bash
# Check code style
composer format

```

## Testing

All contributions must include appropriate tests:

```bash
# Run all tests
composer test

# Run tests with coverage
composer test-coverage

# Run specific test file
./vendor/bin/pest tests/Unit/SettingsTest.php
```

### Writing Tests

- Use **Pest PHP** for testing
- Test both **happy path and edge cases**
- Include tests for **multi-tenant scenarios**
- Mock external dependencies when appropriate

Example test structure:
```php
it('can set and get global settings', function () {
    Settings::set('test_key', 'test_value');
    
    expect(Settings::get('test_key'))->toBe('test_value');
});

it('can set tenant-specific settings', function () {
    $user = User::factory()->create();
    
    Settings::for($user)->set('theme', 'dark');
    
    expect(Settings::for($user)->get('theme'))->toBe('dark');
});
```

## Documentation

- Update **README.md** for new features
- Add **inline documentation** for complex methods
- Include **usage examples** in docblocks
- Update **configuration examples** when needed

## Commit Messages

Use clear, descriptive commit messages:

```
feat: add support for custom cache stores
fix: resolve tenant isolation issue
docs: update installation instructions
test: add tests for polymorphic settings
refactor: improve type casting performance
```

Prefix types:
- `feat:` New features
- `fix:` Bug fixes
- `docs:` Documentation changes
- `test:` Test additions/changes
- `refactor:` Code refactoring
- `style:` Code style changes
- `chore:` Maintenance tasks

## Review Process

1. **Automated checks** must pass (tests, code style)
2. **Manual review** by maintainers
3. **Discussion** if changes are needed
4. **Approval** and merge

## Release Process

Releases follow [Semantic Versioning](https://semver.org/):

- **MAJOR**: Breaking changes
- **MINOR**: New features (backward compatible)
- **PATCH**: Bug fixes (backward compatible)

## Getting Help

- **GitHub Issues**: For bugs and feature requests
- **GitHub Discussions**: For questions and general discussion
- **Email**: Contact maintainers directly for sensitive issues

## Recognition

Contributors will be acknowledged in:
- **CHANGELOG.md** for their contributions
- **README.md** contributors section
- **GitHub contributors** page

Thank you for helping make laravel-setanjo better! 🚀