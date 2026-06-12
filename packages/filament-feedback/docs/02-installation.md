---
title: Installation
---

# Installation

## Composer

```bash
composer require aiarmada/filament-feedback
```

## Publish config

```bash
php artisan vendor:publish --tag=filament-feedback-config
```

## Register plugin

```php
use AIArmada\FilamentFeedback\FilamentFeedbackPlugin;

$panel
    ->plugin(FilamentFeedbackPlugin::make());
```
