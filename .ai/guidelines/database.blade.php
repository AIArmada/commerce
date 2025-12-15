# Database Guidelines
- **PK**: `uuid('id')->primary()`.
- **FK**: `foreignUuid('col')` only. NO `constrained()` or DB-level cascades.
- **Cascades**: Handle in Application Logic (Model/Service).
- **Schema**: No `down()` logic needed.
- **Rules**: Ensure migrations are safe and idempotent.