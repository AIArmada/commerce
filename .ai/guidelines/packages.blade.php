# Packages Guidelines
- **Independence**: Packages must work standalone. Prefer `suggest` over hard `require` for optional integrations.
- **Foundation-first**: Always check `commerce-support` for existing primitives, traits, helpers, and contracts before building custom logic or requiring external packages directly.
- **Standardize shared capabilities**: If functionality is useful across packages (now or soon), implement it in `commerce-support` so behavior stays consistent and maintainable long term.
- **Money & currency**: Treat money as integer minor units plus an explicit currency code. Use `commerce-support` money primitives before rolling your own: `MoneyNormalizer` for normalization, `FormatsMoney` or Akaunting `money(..., ..., false)` for display/value formatting, and package/domain `Money` objects where contracts already expect them. Do **not** hand-roll currency display with raw `number_format()` and string concatenation when a shared formatter is available.
- **Integration**: When related packages are installed together, auto-enable integrations via `class_exists()` checks in service providers.
- **DTOs**: Use `spatie/laravel-data`.
- **Deletes**: No soft deletes (`SoftDeletes`).
- **Testing**: Verify both standalone install and integrated behavior.
