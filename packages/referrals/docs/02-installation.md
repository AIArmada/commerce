---
title: Referrals Installation
---

# Referrals Installation

## Install

```bash
composer require aiarmada/referrals
```

The service provider is registered through Laravel package discovery. It merges `config/referrals.php`, loads the package migrations, and registers the `referrals:expire-invites` command in console applications.

Publish the config when you need to change table names, owner behavior, expiry defaults, or fulfilment drivers:

```bash
php artisan vendor:publish --tag=referrals-config
```

Run the host application's migrations:

```bash
php artisan migrate
```

## Optional fulfilment rails

The core package does not require the optional packages. Install only the rail you use:

```bash
composer require aiarmada/vouchers
composer require aiarmada/affiliates
composer require aiarmada/rewards
```

Enable a driver in `referrals.fulfillment.drivers`. For affiliates cash, bind both a host `ReferralAffiliateResolver` and an `AffiliateAwardPolicy`; the policy binding is shared by all affiliate awards in the host. See [Usage](04-usage.md) for the policy requirements and Rewards source-fact integration.
