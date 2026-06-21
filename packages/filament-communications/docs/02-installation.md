---
title: Installation
---

# Installation

## Requirements

- PHP 8.4+
- Laravel 13.x
- Filament 5.x
- `aiarmada/communications`

## Install

```bash
composer require aiarmada/filament-communications
```

## Publish config

```bash
php artisan vendor:publish --provider="AIArmada\Filament\Communications\FilamentCommunicationsServiceProvider" --tag="filament-communications-config"
```
