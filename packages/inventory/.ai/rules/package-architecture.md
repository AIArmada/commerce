# Inventory Package Architecture

Namespace: `AIArmada\Inventory`. Layout: `modular`.

- Own resources through feature.json manifests and write only through the owning feature's Actions or Persistence. Cross-feature collaboration uses public Api contracts.
- Keep entry points thin and delegate durable mutations.
- Do not import host App classes, mutate application globals, call env outside config or add inline static-analysis suppressions.
- Keep Contracts and Data pure; no Eloquent, HTTP types, Actions or Drivers.
- Update consumers directly and delete superseded code. No compatibility shims or backfill.
- Run composer arch:check, PHPStan, Pest and Pint before declaring completion.
