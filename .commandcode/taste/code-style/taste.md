# Code Style

- Wants legacy surface area deleted outright rather than preserved: no backward-compatibility shims, deprecated fallback paths, legacy aliases, or dead code. When behavior changes, update the affected tests and docs to match the new contract instead of keeping a compatibility wrapper. Confidence: 0.6
