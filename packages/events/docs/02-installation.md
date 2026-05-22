---
title: Installation
---

# Installation

Install the package through Composer:

```bash
composer require aiarmada/events
```

Then run migrations:

```bash
php artisan migrate
```

If you want to customize table names or integrations, publish the config:

```bash
php artisan vendor:publish --tag=events-config
```

> In the AIArmada monorepo, the package is auto-discovered once Composer autoloads are refreshed.