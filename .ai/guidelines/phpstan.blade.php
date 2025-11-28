# PHPStan Guidelines

PHPStan must pass at level 6 for all code.

## Verification

Run the following command to verify:

```bash
./vendor/bin/phpstan analyse --level=6
```

## Configuration

The project's `phpstan.neon` configures the baseline. Ensure no errors at level 6 before merging changes.
