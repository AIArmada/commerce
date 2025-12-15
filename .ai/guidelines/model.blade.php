# Model Guidelines
- **Base**: `HasUuids`, no `$table` property (use config).
- **Relations**: Typed with generics (PHPDoc).
- **Cascades**: Handle in `booted()` (delete/null). NO DB cascades.
- **Migration**: `foreignUuid()` only.