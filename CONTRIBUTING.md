# Contributing to Daraza Payments Gateway

Thank you for your interest in contributing to the Daraza Payments Gateway plugin! This document provides guidelines and instructions for contributing to this project.

## Code of Conduct

By participating in this project, you agree to maintain a respectful and inclusive environment for everyone. We expect all contributors to:

- Be respectful and considerate of others
- Accept constructive criticism gracefully
- Focus on what's best for the community
- Show empathy towards other community members

## How Can I Contribute?

### Reporting Bugs

Before creating bug reports, please check the issue list as you might find out that you don't need to create one. When you are creating a bug report, please include as many details as possible:

- Use a clear and descriptive title
- Describe the exact steps to reproduce the problem
- Provide specific examples to demonstrate the steps
- Describe the behavior you observed after following the steps
- Explain which behavior you expected to see instead and why
- Include screenshots if applicable
- Include your WordPress, PHP, and WooCommerce versions
- Include any relevant error messages

### Suggesting Enhancements

We love to hear your ideas for improving the plugin! When suggesting enhancements:

- Use a clear and descriptive title
- Provide a detailed description of the proposed functionality
- Explain why this enhancement would be useful
- List any similar features in other plugins if applicable
- Include mockups or screenshots if relevant

### Pull Requests

1. Fork the repository
2. Create a new branch for each feature or bugfix
3. Follow our coding standards
4. Add or update tests as needed
5. Update documentation to reflect your changes
6. Submit a pull request

## Development Setup

### Prerequisites

- WordPress development environment
- PHP 7.4 or higher
- Composer
- Node.js and npm
- Git

### Local Development

1. Clone the repository:
   ```bash
   git clone https://github.com/your-username/daraza-payments.git
   cd daraza-payments
   ```

2. Install dependencies:
   ```bash
   composer install
   npm install
   ```

3. Set up your development environment:
   - Configure your local WordPress installation
   - Set up a test WooCommerce store
   - Configure your Daraza API credentials

## Coding Standards

### PHP Standards

- Follow [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/php/)
- Use PHP 7.4+ features where appropriate
- Document all functions and classes using PHPDoc
- Write unit tests for new functionality

### JavaScript Standards

- Follow [WordPress JavaScript Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/javascript/)
- Use ES6+ features where appropriate
- Write unit tests for new functionality

### CSS Standards

- Follow [WordPress CSS Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/css/)
- Use SCSS for stylesheet development
- Follow BEM naming convention

## Testing

### PHP Unit Tests

Run the test suite:
```bash
composer test
```

### JavaScript Tests

Run the test suite:
```bash
npm test
```

### Manual Testing

Before submitting a pull request, please test your changes:

1. In a clean WordPress installation
2. With different WordPress versions (5.6+)
3. With different WooCommerce versions (8.0+)
4. With different PHP versions (7.4+)
5. With different themes
6. With different payment methods
7. With different currencies

## Documentation

- Update the README.md if necessary
- Add or update inline documentation
- Update the changelog
- Document any new hooks or filters
- Update API documentation if applicable

## Commit Messages

- Use the present tense ("Add feature" not "Added feature")
- Use the imperative mood ("Move cursor to..." not "Moves cursor to...")
- Limit the first line to 72 characters or less
- Reference issues and pull requests liberally after the first line

## Review Process

1. All pull requests require at least one review
2. All tests must pass
3. Code must meet our coding standards
4. Documentation must be updated
5. Changes must be properly tested

## Release Process

1. Version bump in plugin header
2. Update changelog
3. Create release tag
4. Update documentation
5. Deploy to WordPress repository

## Questions?

If you have any questions about contributing, please:

- Open an issue
- Contact the maintainers
- Join our [community forum](https://daraza.net/community)

Thank you for contributing to Daraza Payments Gateway!

---

*This contributing guide is adapted from the [Atom contributing guide](https://github.com/atom/atom/blob/master/CONTRIBUTING.md) and [WordPress contributing guidelines](https://make.wordpress.org/core/handbook/contribute/).* 