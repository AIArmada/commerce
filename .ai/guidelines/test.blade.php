# Testing Guidelines

## Running Tests

Use `--parallel` flag to speed up test execution:

```bash
./vendor/bin/pest tests/src/PackageName --parallel
```

## Fixing Multiple Test Failures

When fixing tests that have many failures:

1. **Record failures first** - Run tests once and capture all failing test names/locations to a file before making any fixes
2. **Analyze patterns** - Group failures by root cause (e.g., missing field, wrong assertion, invalid test data)
3. **Batch fixes** - Fix all related issues together before re-running tests
4. **Avoid repeated runs** - Test suites are large and slow; minimize full test runs by:
   - Fixing all identified issues in one pass
   - Running only the specific test file during development: `./vendor/bin/pest tests/path/to/TestFile.php`
   - Using `--filter` to run specific test cases when debugging

Example workflow:
```bash
# 1. Run once and capture failures
./vendor/bin/pest tests/src/PackageName --configuration=.xml/package.xml 2>&1 | tee test-failures.txt

# 2. Fix all issues based on the captured output

# 3. Run specific test file to verify fixes
./vendor/bin/pest tests/src/PackageName/Unit/SpecificTest.php --configuration=.xml/package.xml

# 4. Run full suite only after individual files pass
./vendor/bin/pest tests/src/PackageName --parallel --configuration=.xml/package.xml
```

## Coverage

- Scope coverage to specific packages using dedicated PHPUnit XML configs inside .xml folder (e.g., `cart.xml`, `vouchers.xml`).
- Create `package.xml` if it doesn't exist, following the structure of existing ones (bootstrap autoload, testsuite directory, source include, env vars).
- Run coverage:

```bash
./vendor/bin/phpunit .xml/package.xml --coverage
```

- All non filament packages must achieve **minimum 85% coverage**.
- Verify with `./vendor/bin/pest --coverage --min=85` for workspace-wide checks when applicable.
