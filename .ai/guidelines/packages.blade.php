# Packages Guidelines

## Independence
- Packages must work fully standalone without requiring other commerce packages.
- Use `suggest` or optional dependencies in `composer.json`, not `require`.

## Tight Integration
- When related packages are installed together, enable seamless integrations:
  - Auto-setup relations, events, middleware via service provider checks.
  - Use `class_exists()` or `config('package.enabled')` for conditional features.

## Example Service Provider
```php
public function boot(): void
{
    if (class_exists(Cashier::class)) {
        // Cart-Cashier integration
    }
    
    if (class_exists(Chip::class)) {
        // Cart-Chip integration
    }
}
```

## Verification
- Test standalone: `composer require package/cart`
- Test integrated: Install multiple, verify auto-features.
