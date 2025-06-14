# Security Policy

## Supported Versions

We take security seriously and provide security updates for the following versions of laravel-setanjo:

| Version | Supported          | Laravel Compatibility |
| ------- | ------------------ | -------------------- |
| 1.x.x   | :white_check_mark: | Laravel 10.x, 11.x, 12.x  |
| < 1.0   | :white_check_mark: | Laravel 10.x, 11.x, 12.x  |

**Note**: Only the latest major version receives security updates. We recommend keeping your installation up to date with the latest stable release.

## Reporting a Vulnerability

We appreciate responsible disclosure of security vulnerabilities. If you discover a security issue, please follow these steps:

### How to Report

**Please DO NOT create a public GitHub issue for security vulnerabilities.**

Instead, report security issues privately by:

1. **Email**: Send details to [mdazizulhakim.cse@gmail.com](mdazizulhakim.cse@gmail.com) or the package maintainer directly
2. **GitHub Security Advisory**: Use GitHub's private vulnerability reporting feature


### What to Include

When reporting a security vulnerability, please include:

- **Description** of the vulnerability and its potential impact
- **Steps to reproduce** the issue with detailed instructions
- **Affected versions** or version ranges
- **Proof of concept** code or screenshots (if applicable)
- **Suggested fix** or mitigation (if you have ideas)
- **Your contact information** for follow-up questions

### Response Timeline

We are committed to responding to security reports promptly:

- **Initial Response**: Within 48 hours of report
- **Assessment**: Within 7 days we'll provide initial assessment
- **Fix Development**: Critical issues will be prioritized for immediate fixes
- **Disclosure**: Coordinated disclosure after fix is available

### What to Expect

**If the vulnerability is accepted:**
- We'll work with you to understand and reproduce the issue
- Develop and test a fix
- Release a security patch
- Credit you in the security advisory (if desired)
- Coordinate public disclosure timing

**If the vulnerability is declined:**
- We'll explain why it's not considered a security issue
- Provide guidance if it's a configuration or usage issue
- Suggest alternative reporting channels if appropriate

## Security Considerations

### Multi-Tenant Security

This package handles multi-tenant data. Key security considerations:

- **Tenant Isolation**: Settings are properly isolated between tenants
- **Authorization**: Validate tenant access before reading/writing settings
- **Model Validation**: Ensure only allowed models can be used as tenants

### Best Practices

When using laravel-setanjo:

1. **Validate Input**: Always validate setting values before storage
2. **Sanitize Output**: Be cautious when displaying user-provided setting values
3. **Access Control**: Implement proper authorization for setting management
4. **Audit Trail**: Consider logging sensitive setting changes
5. **Cache Security**: Ensure cache stores are properly secured

### Known Security Considerations

- Settings stored in database are not encrypted by default
- Cache invalidation timing may expose information about setting changes
- Polymorphic mode requires careful tenant model validation

## Security Updates

Security updates will be:

- Released as patch versions (e.g., 1.0.x)
- Documented in [CHANGELOG.md](CHANGELOG.md) with security labels
- Announced through GitHub releases
- Tagged with `security` label

## Acknowledgments

We thank the security research community for helping keep laravel-setanjo secure. Security researchers who responsibly disclose vulnerabilities will be acknowledged in:

- Security advisories
- CHANGELOG.md
- Hall of fame (if established)

## Contact

For security-related questions or concerns:

- **Security Issues**: Use private reporting methods above
- **General Security Questions**: Create a GitHub discussion
- **Documentation**: Suggest improvements via pull request

---

**Remember**: Security is everyone's responsibility. If you're unsure whether something is a security issue, err on the side of caution and report