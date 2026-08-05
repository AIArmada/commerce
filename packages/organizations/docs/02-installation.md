---
title: Organizations Installation
---

## Install

Require the package together with its required support and membership
dependencies:

```bash
composer require aiarmada/organizations
php artisan migrate
```

The package registers its service provider through Laravel package discovery
and publishes the `organizations` configuration file through the package tools
conventions.

## Application integration

Bind `AIArmada\Organizations\Contracts\CurrentOrganizationResolver` to the
application resolver, then apply
`AIArmada\Organizations\Http\Middleware\CurrentOrganizationMiddleware` to
routes that need organization context.
