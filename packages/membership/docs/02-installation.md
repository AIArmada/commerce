---
title: Membership Installation
---

## Install

```bash
composer require aiarmada/membership
php artisan vendor:publish --tag=membership-config
```

Add `HasMembers` to each membership subject and create its pivot migration in
the host application. Membership does not generate host application files.
The migration must use the subject key and the configured suffix (the default
for `Team` is `team_members`):

```bash
php artisan migrate
```

See [Usage: Pivot migration](04-usage.md#pivot-migration) for the complete
copy-paste migration.
