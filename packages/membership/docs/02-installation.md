---
title: Membership Installation
---

## Install

```bash
composer require aiarmada/membership
php artisan vendor:publish --tag=membership-config
php artisan migrate
```

Add `HasMembers` to each membership subject and generate its pivot migration:

```bash
php artisan membership:make-pivot "App\Models\Team"
php artisan migrate
```
