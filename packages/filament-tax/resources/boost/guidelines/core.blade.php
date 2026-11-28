# FilamentTax

Filament Tax is an administration entry point, not a data owner. Delegate tax mutations to Tax actions, resolve records inside strict owner scope, authorize each administrative operation, and never mutate global records from a tenant context. Keep settings mutations in UpdateTaxSettingsAction.

Do not introduce compatibility wrappers, dual writes, or backfills.
