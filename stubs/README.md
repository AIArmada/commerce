# Static Analysis Stubs

Files under `stubs/` are support files for tools such as PHPStan.

## Important boundaries

- These files are **not runtime code**.
- These files are **not API documentation**.
- They exist to give static-analysis tools enough symbol information for vendor libraries that are hard to infer directly.

When documenting or implementing behavior, prefer:

1. `packages/*/src`
2. `packages/*/docs`
3. active root docs under `docs/`

Use the stubs only to understand why static analysis succeeds or why a tool-only shim exists.