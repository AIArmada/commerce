# Filament Addressing Instruction Pack Notes

Generated for `aiarmada/filament-addressing`.

This instruction pack assumes the core `aiarmada/addressing` package already exists or is being created first.

Key decisions:

- Filament package is adapter only.
- No migrations in this package.
- Country and area resources enabled by default.
- Address and snapshot resources disabled by default.
- Countries read-only by default.
- Snapshots read-only.
- Area import/export uses Filament built-ins and core addressing actions.
- Reusable schema and relation manager are opt-in for downstream resources.
