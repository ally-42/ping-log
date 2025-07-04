# Contributing to PHP Site Monitor

Thank you for your interest in contributing to PHP Site Monitor! This document provides guidelines for contributing to this project.

## How to Contribute

### Reporting Bugs

1. Check if the bug has already been reported in the [Issues](https://github.com/your-username/ping-log/issues) section
2. Create a new issue using the bug report template
3. Provide detailed information about the bug, including:
   - Steps to reproduce
   - Expected vs actual behavior
   - Environment details (OS, PHP version, etc.)
   - Configuration examples (without sensitive data)

### Suggesting Features

1. Check if the feature has already been requested
2. Create a new issue using the feature request template
3. Describe the use case and benefits of the feature
4. Provide examples of how the feature would work

### Submitting Code Changes

1. Fork the repository
2. Create a new branch for your changes: `git checkout -b feature/your-feature-name`
3. Make your changes following the coding standards below
4. Test your changes thoroughly
5. Commit your changes with a descriptive message
6. Push to your fork and create a Pull Request

## Coding Standards

### PHP Code Style

- Use PSR-12 coding standards
- Use meaningful variable and function names
- Add comments for complex logic
- Keep functions small and focused
- Use English for all code comments and strings

### Git Commit Messages

Use conventional commit format:
```
type(scope): description

[optional body]

[optional footer]
```

Examples:
- `feat: add support for custom timeout per site`
- `fix: resolve webhook sending issue`
- `docs: update README with new configuration options`

### Testing

Before submitting a PR, ensure:
1. The script runs without errors
2. All functions work as expected
3. Log files are created correctly
4. Webhook alerts are sent properly (test with a real webhook)

## Development Setup

1. Clone the repository
2. Create a `.env` file based on `.env.example`
3. Configure test sites and webhooks
4. Run the script: `php index.php`

## Questions or Need Help?

If you have questions or need help, please:
1. Check the [README.md](README.md) for documentation
2. Search existing issues for similar questions
3. Create a new issue with the "question" label

Thank you for contributing to PHP Site Monitor! 🚀 