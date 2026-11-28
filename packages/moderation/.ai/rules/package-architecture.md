---
paths:
  - src/**
  - config/**
  - tests/**
---

# Laravel Package Architecture Rules (`AIArmada\Moderation`)

## Layout
This focused package uses **single-capability layout (`src/`)**. Writes live in `Actions/` or `Persistence/`; model traits delegate to concrete actions.

## Package-Safety Laws
- Never import host application code (`App\\*`) inside `src/`.
- Never mutate host globals (`Model::shouldBeStrict()`, `Model::unguard()`, `DB::prohibitDestructiveCommands()`, `Date::use()`).
- Never call `env()` outside `config/`, `sleep()`/`Sleep::sleep()`, `dd()`/`dump()`/`ray()`, or add inline `@phpstan-ignore` suppressions.
- Never keep backward-compatibility shims, legacy wrappers, or leftover dead files; update callers and delete superseded code in the same change.
- Keep `src/Contracts/` and `src/Data/` free of Eloquent models, `Illuminate\\Http\\*`, Actions, and Drivers.
- Run `composer arch:check`, `vendor/bin/phpstan analyse`, `vendor/bin/pest`, and `vendor/bin/pint --test` before declaring done.
