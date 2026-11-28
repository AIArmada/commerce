# Products Package Architecture

Namespace: `AIArmada\Products`. Layout: `single`.

- Own durable writes in src/Actions or src/Persistence; use Drivers for external systems.
- Keep entry points thin and delegate durable mutations.
- Do not import host App classes, mutate application globals, call env outside config or add inline static-analysis suppressions.
- Keep Contracts and Data pure; no Eloquent, HTTP types, Actions or Drivers.
- Update consumers directly and delete superseded code. No compatibility shims or backfill.
- Run composer arch:check, PHPStan, Pest and Pint before declaring completion.
