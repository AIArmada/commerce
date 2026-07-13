# AIArmada Authz

Framework-agnostic Spatie Permission integration with UUID permission schema, scopes, wildcard permissions, and impersonation services.

See [docs/](docs/) for installation, configuration, and usage.

## Session security

Taking and leaving impersonation destroys the previous session identifier and regenerates the CSRF token after the identity transition succeeds.
