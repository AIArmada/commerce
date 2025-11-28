# Config Guidelines

All configuration options must be actively used or implemented in the codebase.

## Rules
- If a config key is defined but not referenced anywhere, remove it.
- Publish only necessary configs via `php artisan vendor:publish`.
- Keep `config/*.php` files minimal and purposeful.

## Verification
Search codebase for config key usage:
```bash
grep -r "config('package.key')" src/ packages/*/src/
```
If no matches, remove the config.
