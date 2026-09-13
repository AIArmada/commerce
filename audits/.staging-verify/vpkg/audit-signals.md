### Prior-audit section
### signals
Bugs:
- `IngestSignalEvent:260-262` MEDIUM-HIGH privacy — `'*'` allowlist skips PII blocklist.
- Browser events no idempotency MEDIUM — `idempotencyKey` trusted-only `:47-57`; retries duplicate rows.
- `revenue_minor (int)` cast LOW — truncates floats, accepts negatives.
Security:
- `write_key random(40)` bearer in query LOW — plaintext; sent as `data-write-key` + body/query → log leak. Prefer header/body.
- Trusted/browser ingestion GOOD — strict timestamp/replay/format/`hash_equals`/RateLimiter dedup; per-prop/IP limits.
Performance: GOOD — async geocode default; queued alert eval; reports eager-load; `(tracked_property,idempotency_key)` unique.
