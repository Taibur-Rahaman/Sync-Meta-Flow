# Security Policy

## Supported versions

The `main` branch and the latest tagged release are the supported versions.

## Reporting a vulnerability

Please do not publish security vulnerabilities in a public GitHub issue. Contact the maintainer privately through the repository owner's GitHub profile and include:

- affected version
- affected file or feature
- reproducible steps
- impact
- suggested mitigation, if known

Never include Meta access tokens, courier API keys, webhook secrets, customer passwords, payment data, or other credentials in a report.

## Security principles

Sync Meta Flow should:

- verify WordPress capabilities and nonces for privileged admin actions;
- authenticate courier webhooks with HMAC before processing;
- avoid placing API credentials in URLs;
- never expose stored secrets in diagnostics;
- sanitize and validate external input;
- use prepared SQL queries for dynamic values;
- minimize customer data sent to third-party APIs.
