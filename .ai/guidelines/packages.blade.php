# Packages Guidelines
- **Indep**: Must run standalone. `suggest` over `require`.
- **Integ**: Auto-enable via `class_exists()` check in `boot()`.
- **Code**: All DTOs via `spatie/laravel-data`.
- **Deletes**: No soft deletes (`SoftDeletes`).
- **Test**: Verify standalone install and integration.
