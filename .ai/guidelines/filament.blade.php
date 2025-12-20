# Filament Guidelines
- **Version**: Filament v5.
  - Filament v5 is API-compatible with Filament v4; the main difference is Livewire (v5 uses Livewire v4, v4 uses Livewire v3).
  - When v5 docs are incomplete, v4 docs/examples are acceptable.
- **Spatie**: MUST use official Filament plugins (Tags, Settings, Media, Fonts).
- **Actions**: Use built-in `Import`/`Export` actions only.
- **Multitenancy**: Filament tenancy is NOT sufficient; all queries and action handlers must still obey the owner-scoping contract.

## Verification
- Double-check method signatures in the installed Filament version before shipping.