# Upgrade Guide

This guide covers upgrading between versions of AIArmada Commerce packages.

## Version 0.1.0 (Initial Release)

**Release Date**: November 2025

This is the initial public release of AIArmada Commerce. No upgrade path needed.

### What's New

- Complete monorepo with 10 packages
- Support for Laravel 12 and PHP 8.4
- Filament 5 admin panels
- Comprehensive documentation
- Pest v4 testing with browser support

### System Requirements

- PHP: ^8.4 (baseline raised from ^8.2)
- Laravel: ^12.0
- Filament: ^5.0 (optional, for admin panels)
- PostgreSQL: 14+ (recommended) or MySQL 8+

### Migration & Schema Changes

- Database portability: JSON columns are now created as `json` by default across all drivers. Engine-specific features like `jsonb` and raw GIN indexes are opt-in.
- PostgreSQL opt-in: To use `jsonb` on fresh installs, set `COMMERCE_JSON_COLUMN_TYPE=jsonb` (or per-package overrides like `VOUCHERS_JSON_COLUMN_TYPE=jsonb`, `CART_JSON_COLUMN_TYPE=jsonb`, `CHIP_JSON_COLUMN_TYPE=jsonb`) before running migrations. GIN indexes are created automatically for vouchers tables and selected cart/CHIP fields when using PostgreSQL.
- UUID consistency: Voucher IDs and related foreign keys use UUIDs across the vouchers domain.

### Configuration Changes

- Vouchers cart behavior: New `vouchers.cart.replace_when_max_reached` controls how to handle adding a voucher when the cart already has the maximum allowed.

## Future Upgrade Paths

### General Upgrade Process

When upgrading between versions:

1. **Check Upgrade Notes**: Read release notes for breaking changes
2. **Update Dependencies**: Run `composer update`
3. **Run Migrations**: Execute `php artisan migrate`
4. **Clear Caches**: Run `php artisan optimize:clear`
5. **Test Thoroughly**: Run your test suite

### Version-Specific Guides

Future versions will have dedicated upgrade sections here.

## Breaking Change Policy

We follow semantic versioning (SemVer):

- **Major versions** (2.0.0): May contain breaking changes
- **Minor versions** (0.2.0): New features, backward compatible
- **Patch versions** (0.1.1): Bug fixes, backward compatible

### What Constitutes a Breaking Change

- Removing or renaming public methods/properties
- Changing method signatures
- Modifying database schema without migration path
- Changing configuration keys
- Removing events or changing event data

### What Is NOT a Breaking Change

- Adding new methods/properties
- Fixing bugs that restore intended behavior
- Internal refactoring
- Adding optional parameters
- Deprecating (but not removing) features

## Migration Best Practices

### Before Upgrading

1. **Backup Database**: Always backup before major upgrades
   ```bash
   php artisan db:backup
   ```

2. **Review Changelog**: Check `CHANGELOG.md` for each package

3. **Test in Staging**: Never upgrade directly in production

4. **Check Dependencies**: Ensure all packages are compatible
   ```bash
   composer outdated
   ```

### During Upgrade

1. **Update Composer**: Get latest versions
   ```bash
   composer update aiarmada/*
   ```

2. **Run Migrations**: Apply database changes
   ```bash
   php artisan migrate
   ```

3. **Clear Caches**: Fresh start
   ```bash
   php artisan optimize:clear
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   ```

4. **Verify Tests**: Ensure everything works
   ```bash
   composer test
   ```

### After Upgrading

1. **Monitor Logs**: Watch for errors
   ```bash
   tail -f storage/logs/laravel.log
   ```

2. **Test Critical Paths**: Manually test key features
   - Cart operations
   - Payment processing
   - Voucher redemption
   - Shipping calculations

3. **Update Documentation**: If you maintain custom docs

## Deprecation Policy

Features may be deprecated before removal:

1. **Deprecation Notice**: Feature marked as deprecated in minor version
2. **Alternative Provided**: Documentation shows replacement
3. **Grace Period**: At least one major version before removal
4. **Removal**: Feature removed in next major version

### Handling Deprecated Features

```php
// Deprecated in 0.5.0, removed in 1.0.0
Cart::addItem($id, $name, $price); // ⚠️ Deprecated

// Use new method
Cart::add($id, $name, $price); // ✅ Recommended
```

## Rollback Strategy

If upgrade causes issues:

### 1. Revert Composer Dependencies

```bash
# Restore previous version
composer require aiarmada/commerce:0.1.0

# Clear caches
php artisan optimize:clear
```

### 2. Rollback Database Migrations

```bash
# Rollback last batch
php artisan migrate:rollback

# Or rollback to specific version
php artisan migrate:rollback --step=5
```

### 3. Restore Configuration

```bash
# Restore from version control
git checkout HEAD -- config/

# Republish if needed
php artisan vendor:publish --tag=commerce-config --force
```

## Support for Older Versions

- **Current major version**: Full support
- **Previous major version**: Security fixes only
- **Older versions**: No support

### Example Timeline

| Version | Released | Support Until | Security Fixes Until |
|---------|----------|---------------|---------------------|
| 1.0.0 | Jan 2026 | Jan 2027 | Jan 2028 |
| 0.9.0 | Dec 2025 | Jan 2026 | Jul 2026 |
| 0.1.0 | Nov 2025 | Dec 2025 | Jun 2026 |

## Getting Help with Upgrades

### Community Support

- **GitHub Discussions**: Ask upgrade questions
- **GitHub Issues**: Report upgrade problems
- **Documentation**: Check package-specific guides

### Commercial Support

For priority upgrade assistance:
- Email: info@aiarmada.com
- Include: Current version, target version, error messages

## Pre-Release Testing

Help test upcoming releases:

```bash
# Install beta version
composer require aiarmada/commerce:^1.0@beta

# Install alpha version
composer require aiarmada/commerce:^1.0@alpha

# Install dev version
composer require aiarmada/commerce:dev-main
```

⚠️ **Warning**: Pre-release versions are not production-ready.

## Next Steps

- **[Deployment Guide](06-deployment.md)**: Production deployment checklist
- **[Package Reference](03-packages/)**: Package-specific documentation
- **[Support Utilities](04-support-utilities.md)**: Shared utilities across packages
