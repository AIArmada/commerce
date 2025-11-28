# Testing Guidelines

## Running Tests

Use `--parallel` flag to speed up test execution:

```bash
./vendor/bin/pest tests/src/PackageName --parallel
```

## Coverage

- Scope coverage to specific packages using dedicated PHPUnit XML configs (e.g., `cart.xml`, `vouchers.xml`).
- Create `package.xml` if it doesn't exist, following the structure of existing ones (bootstrap autoload, testsuite directory, source include, env vars).
- Run coverage:

```bash
./vendor/bin/phpunit package.xml --coverage
```

- All packages must achieve **minimum 85% coverage**.
- Verify with `./vendor/bin/pest --coverage --min=85` for workspace-wide checks when applicable.
