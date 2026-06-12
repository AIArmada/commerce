# Packages Guidelines

## Package Boundaries
- Packages must work standalone. Prefer `suggest` over hard `require` for optional integrations.
- When related packages are installed together, auto-enable integrations in service providers with `class_exists()` checks.

## Shared Foundations
- Always check `commerce-support` for existing primitives, traits, helpers, and contracts before building custom logic or requiring an external package directly.
- If a capability is useful across packages now or soon, implement it in `commerce-support` so behavior stays consistent and maintainable long term.
- When a capability may grow variants, prefer stable extension seams such as contracts, metadata, hooks, domain events, resolvers, and support classes. Put shared seams in `commerce-support` when multiple packages may benefit.
- Prefer tagged registrars or contributor interfaces for optional integrations instead of hard-coded service-provider branching.
- Keep foundation service providers lean. If they start enumerating downstream packages, split the registration into registrars or support classes.
- When orchestration repeats across HTTP, jobs, listeners, commands, or UI entry points, extract a reusable Action, Service, or Use Case.

## Money And Storage
- Treat money as integer minor units plus an explicit currency code.
- Use `commerce-support` money primitives before rolling your own: `MoneyNormalizer` for normalization, `FormatsMoney` or Akaunting `money(..., ..., false)` for display or value formatting, and package or domain `Money` objects where contracts already expect them.
- Do not hand-roll currency display with raw `number_format()` and string concatenation when a shared formatter is available.
- No soft deletes (`SoftDeletes`).

## Verification
- Verify both standalone install and integrated behavior.
