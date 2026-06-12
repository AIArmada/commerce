# Spatie Guidelines

## Preferred Packages
- DTOs: `spatie/laravel-data`
- Logging: `activitylog` for business events, `auditing` for compliance
- Webhooks: `spatie/laravel-webhook-client` for the idempotent job pattern
- Media: `spatie/laravel-medialibrary`
- Settings: `spatie/laravel-settings`
- Tags: `spatie/laravel-tags`
- States: `spatie/laravel-model-states`

## Rule Of Thumb
- If one of these packages solves the problem, use it instead of inventing a custom subsystem.
