# Filament Guidelines

## Platform Rules
- Use Filament v5 APIs.
- Filament v5 is the target surface. If v5 documentation is thin, the equivalent v4 examples are acceptable because the APIs are compatible.
- Use the official Filament plugins for Tags, Settings, Media, and Fonts when those capabilities are needed.
- Use the built-in `Import` and `Export` actions only.

## Tenancy
- Filament tenancy is not a security boundary. All queries and all action handlers must still obey the owner-scoping contract.

## Verification
- Double-check method signatures in the installed Filament version before shipping.
