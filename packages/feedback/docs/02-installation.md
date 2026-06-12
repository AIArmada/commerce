---
title: Installation
---

# Installation

## Composer

```bash
composer require aiarmada/feedback
```

## Service provider

Auto-discovered by Laravel's package discovery.

## Publishing config

```bash
php artisan vendor:publish --tag=feedback-config
```

## Running migrations

```bash
php artisan migrate
```

## Seeding templates

```bash
php artisan db:seed --class="AIArmada\Feedback\Database\Seeders\FeedbackTemplateSeeder"
```

## Optional integration

For integration with events, certificates, or engagement, require the corresponding packages:

```bash
composer require aiarmada/events
composer require aiarmada/certificates
composer require aiarmada/engagement
```
