# Spatie Guidelines
- **DTOs**: `spatie/laravel-data`
- **Logging**: `activitylog` (business events), `auditing` (compliance)
- **Webhooks**: `spatie/laravel-webhook-client` (idempotent job pattern)
- **Media**: `spatie/laravel-medialibrary`
- **Settings**: `spatie/laravel-settings`
- **Tags**: `spatie/laravel-tags`
- **States**: `spatie/laravel-model-states`

## Rule of thumb
- If one of the above solves the problem, prefer it over inventing a custom subsystem.