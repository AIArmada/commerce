---
title: Rewards installation
---

# Rewards installation

The package is part of the commerce monorepo and installs with it.

```bash
composer require aiarmada/rewards
```

The service provider registers automatically. Publish the config when you need to change defaults:

```bash
php artisan vendor:publish --tag=rewards-config
```

Run the migrations:

```bash
php artisan migrate
```

## Requirements

- PHP 8.5+
- `aiarmada/commerce-support`
- A bound `OwnerResolverInterface` when `rewards.owner.enabled` is true (see [configuration](03-configuration.md)).

Next: [configuration](03-configuration.md).
