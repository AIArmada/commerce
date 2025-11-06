# Contributing

Thank you for considering contributing to the Commerce monorepo!

> Parts of this guide are adapted from Filament's contribution guide, which served as valuable inspiration.

## Reporting Bugs

If you discover a bug, please [open an issue](https://github.com/aiarmada/commerce/issues/new) on GitHub. Before opening, search [existing issues](https://github.com/aiarmada/commerce/issues) to check if it's already been reported.

Include as much information as possible: package versions, PHP version, steps to reproduce, and expected vs actual behavior.

## Proposing New Features

To propose a new feature or improvement, use our [discussion forum](https://github.com/aiarmada/commerce/discussions) on GitHub. If you plan to implement it yourself, please discuss with a maintainer beforehand to avoid wasting time.

## Pull Requests

When submitting a pull request:

1. **Fork & Clone** the repository
2. **Create a branch** from `main` (e.g., `fix/cart-condition-error`)
3. **Install dependencies**: `composer install`
4. **Make your changes** following existing patterns
5. **Run quality checks**: `composer ci`
6. **Commit** with a descriptive message
7. **Push** to your fork
8. **Open a PR** with a clear description

### Code Quality

Before submitting, ensure:
- ✅ PHPStan passes: `composer phpstan`
- ✅ Tests pass: `composer test`
- ✅ Code is formatted: `composer format`

## Developing with a Local Copy

To test Commerce in a real Laravel app:

1. Fork [the repository](https://github.com/aiarmada/commerce)
2. Create a Laravel app locally
3. Clone your fork into the app's root: `git clone https://github.com/YOUR_USERNAME/commerce.git`
4. Add to your `composer.json`:

```json
{
    "require": {
        "aiarmada/cart": "*",
        "aiarmada/chip": "*"
    },
    "minimum-stability": "dev",
    "repositories": [
        {
            "type": "path",
            "url": "commerce/packages/*"
        }
    ]
}
```

5. Run `composer update`

## Security Vulnerabilities

If you discover a security vulnerability, please email **security@aiarmada.com**. All security vulnerabilities will be promptly addressed.

## Code of Conduct

Please note that this project is released with a [Contributor Code of Conduct](CODE_OF_CONDUCT.md). By participating, you agree to abide by its terms.
  
vendor/bin/pest packages/chip/tests
```

---

## Code Style

We use **Laravel Pint** for code formatting and **Rector** for automated refactoring.

### Check Code Style

```bash
composer format-test
```

### Fix Code Style

```bash
composer format
```

### Run Rector

```bash
# Preview changes
composer rector-dry

# Apply changes
composer rector
```

### PHPStan

```bash
composer phpstan
```

---

## Pull Request Process

### 1. Create a Feature Branch

```bash
git checkout -b feature/your-feature-name
```

Use prefixes:
- `feature/` - New features
- `fix/` - Bug fixes
- `docs/` - Documentation changes
- `refactor/` - Code refactoring
- `test/` - Test improvements
- `chore/` - Maintenance tasks

### 2. Make Your Changes

- Write clean, readable code
- Add tests for new features
- Update documentation as needed
- Follow existing code patterns

### 3. Run Quality Checks

Before committing, run:

```bash
composer ci
```

Ensure all checks pass:
- ✅ PHPStan: 0 errors
- ✅ Rector: No changes needed
- ✅ Pint: All files formatted
- ✅ Tests: All passing

### 4. Commit Your Changes

Follow the [commit message guidelines](#commit-guidelines):

```bash
git add .
git commit -m "feat: add support for X"
```

### 5. Push to Your Fork

```bash
git push origin feature/your-feature-name
```

### 6. Open a Pull Request

1. Go to the original repository on GitHub
2. Click "New Pull Request"
3. Select your fork and branch
4. Fill in the PR template with:
   - Description of changes
   - Related issues
   - Testing performed
   - Screenshots (if UI changes)

### 7. Code Review

- Address review feedback
- Keep PR up-to-date with main branch
- Be responsive to questions

---

## Package Structure

The Commerce monorepo contains 8 packages:

### Core Packages
- **cart** - Core shopping cart functionality
- **stock** - Inventory management
- **vouchers** - Discount vouchers and promotions

### Integration Packages
- **chip** - CHIP payment gateway integration
- **jnt** - J&T Express shipping integration

### Filament Packages
- **filament-cart** - Filament admin panel for cart management
- **filament-chip** - Filament admin panel for CHIP payments

### Documentation
- **docs** - Shared documentation utilities

### Package Layout

```
packages/
├── cart/
│   ├── src/
│   ├── tests/
│   ├── composer.json
│   └── README.md
├── chip/
│   └── ...
└── ...
```

---

## Commit Guidelines

We follow [Conventional Commits](https://www.conventionalcommits.org/).

### Format

```
<type>(<scope>): <subject>

<body>

<footer>
```

### Types

- `feat` - New feature
- `fix` - Bug fix
- `docs` - Documentation changes
- `style` - Code style changes (formatting, no logic change)
- `refactor` - Code refactoring
- `test` - Adding or updating tests
- `chore` - Maintenance tasks (deps, build, etc.)
- `perf` - Performance improvements
- `ci` - CI/CD changes

### Scopes

- `cart` - Cart package
- `chip` - CHIP package
- `jnt` - J&T package
- `stock` - Stock package
- `vouchers` - Vouchers package
- `filament-cart` - Filament Cart package
- `filament-chip` - Filament CHIP package
- `docs` - Documentation
- `ci` - CI/CD
- `*` - Multiple packages

### Examples

```bash
# Feature
git commit -m "feat(cart): add support for multiple currencies"

# Bug fix
git commit -m "fix(chip): handle webhook signature validation errors"

# Documentation
git commit -m "docs(cart): add examples for condition usage"

# Breaking change
git commit -m "feat(cart)!: change storage driver interface

BREAKING CHANGE: StorageInterface now requires getVersion() method"
```

---

## Testing Guidelines

### Writing Tests

1. **Use Pest** - All tests use Pest, not PHPUnit
2. **Test coverage** - Aim for >80% coverage
3. **Test types**:
   - Unit tests: Test individual classes/methods
   - Feature tests: Test integration between components
   - Browser tests: Test UI functionality (Filament packages)

### Test Structure

```php
<?php

use AIArmada\Cart\Models\Cart;

it('calculates total with conditions', function () {
    $cart = Cart::create();
    
    $cart->addItem([
        'id' => 1,
        'name' => 'Test Product',
        'price' => 100,
        'quantity' => 2,
    ]);
    
    $cart->addCondition([
        'name' => 'VAT',
        'type' => 'tax',
        'value' => '10%',
    ]);
    
    expect($cart->total())->toBe(220.00);
});
```

### Factories

Use factories for test data:

```php
$cart = Cart::factory()->create();
$item = CartItem::factory()->create(['cart_id' => $cart->id]);
```

---

## Documentation Standards

### Package README

Each package should have:
- Installation instructions
- Basic usage examples
- Configuration options
- Testing instructions
- Links to detailed docs

### Code Documentation

- Add PHPDoc blocks for public methods
- Include `@param` and `@return` tags
- Add usage examples in docblocks
- Document complex logic with inline comments

### Changelog

Update `CHANGELOG.md` for each package when making changes.

---

## Monorepo Workflow

### Understanding the Monorepo

This repository uses a monorepo structure where all packages are developed together:

```
packages/
├── cart/              # Core cart engine
├── chip/              # CHIP payment gateway
├── docs/              # Document generation
├── jnt/               # J&T Express API
├── stock/             # Stock management
├── vouchers/          # Voucher system
├── filament-cart/     # Filament cart admin
└── filament-chip/     # Filament CHIP admin
```

On release, packages are automatically split to separate repositories:
- `aiarmada/cart`
- `aiarmada/chip`
- `aiarmada/docs`
- etc.

### Monorepo Commands

```bash
# Merge all package composer.json files
composer monorepo:merge

# Validate package dependencies
composer monorepo:validate

# Bump interdependencies to a version
composer monorepo:bump-interdependency "^2.0"

# Create a release (updates all packages)
composer monorepo:release 2.1.0
```

### Package Development

When developing a new feature:

1. **Work in the monorepo** - Make changes in `packages/your-package/`
2. **Test across packages** - Ensure changes work with dependent packages
3. **Update dependencies** - If you change a package's API, update dependents
4. **Run validation** - `composer monorepo:validate` before committing

### Versioning

All packages use **synchronized versioning**:
- Version numbers are the same across all packages
- A release creates tags for all packages
- Semantic versioning is enforced

### Release Process

Releases are **automated via GitHub Actions**:

#### Creating a Release

1. Ensure all changes are committed and pushed
2. Go to **Actions** → **Create Release** workflow
3. Click **Run workflow**
4. Enter:
   - **Version**: Semver format (e.g., `2.1.0`)
   - **Release type**: major, minor, or patch
5. Click **Run workflow**

The workflow will:
1. ✅ Validate the version format
2. 📝 Update `CHANGELOG.md` (move Unreleased to version)
3. 🔄 Update all package versions and dependencies
4. 🏷️ Create and push git tag
5. 📦 Trigger monorepo split (packages → separate repos)
6. 📋 Create GitHub release with notes
7. ⬆️ Bump to next dev version

#### Manual Release (Advanced)

For manual releases:

```bash
# 1. Update CHANGELOG
vim CHANGELOG.md  # Move [Unreleased] to [2.1.0]

# 2. Run monorepo-builder release
composer monorepo:release 2.1.0

# 3. Create and push tag
git add .
git commit -m "chore: release v2.1.0"
git tag -a v2.1.0 -m "Release v2.1.0"
git push origin main
git push origin v2.1.0
```

#### Package Split

When a tag is pushed, the **Monorepo Split** workflow automatically:

1. Detects the tag (e.g., `v2.1.0`)
2. Splits each package to its own repository:
   - `packages/cart` → `github.com/aiarmada/cart`
   - `packages/chip` → `github.com/aiarmada/chip`
   - etc.
3. Tags each split repository with the same version

This allows:
- Users install individual packages: `composer require aiarmada/cart`
- Each package has its own repository and issues
- Monorepo development continues seamlessly

### Package Dependencies

When packages depend on each other:

```json
{
  "require": {
    "aiarmada/cart": "^2.0",
    "aiarmada/stock": "^2.0"
  }
}
```

Use `composer monorepo:bump-interdependency` to update all cross-package dependencies at once.

### Testing Before Release

Before creating a release:

```bash
# 1. Run full CI suite
composer ci

# 2. Validate monorepo structure
composer monorepo:validate

# 3. Merge and check for conflicts
composer monorepo:merge

# 4. Review changes
git status
git diff
```

### After Release

After a successful release:

1. ✅ Check split repositories have the tag
2. ✅ Verify Packagist updated the versions
3. ✅ Test installing the released versions:
   ```bash
   composer require aiarmada/cart:^2.1
   ```
4. ✅ Check GitHub releases have correct notes
5. 📢 Announce the release (Twitter, Discord, etc.)

---

## Getting Help

- **Questions?** Open a [Discussion](https://github.com/aiarmada/commerce/discussions)
- **Bug?** Open an [Issue](https://github.com/aiarmada/commerce/issues)
- **Security?** Email security@example.com

---

## Recognition

Contributors will be recognized in:
- `CHANGELOG.md`
- GitHub contributors list
- Release notes

Thank you for contributing! 🎉
