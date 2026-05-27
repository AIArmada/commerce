# Domain Docs

How the engineering skills should consume this repo's domain documentation when exploring the codebase.

## Before exploring, read these

- **`CONTEXT-MAP.md`** at the repo root if it exists — it points at one `CONTEXT.md` per context. Read each one relevant to the topic.
- **`CONTEXT.md`** files for the relevant context(s).
- **`docs/adr/`** for system-wide ADRs.
- Context-scoped ADRs under the relevant context directories, such as `src/<context>/docs/adr/` when present.

If any of these files don't exist, proceed silently. Don't flag their absence; don't suggest creating them upfront.

## File structure

Multi-context repo:

```text
/
├── CONTEXT-MAP.md
├── docs/adr/
└── src/
    ├── ordering/
    │   ├── CONTEXT.md
    │   └── docs/adr/
    └── billing/
        ├── CONTEXT.md
        └── docs/adr/
```

## Use the glossary's vocabulary

When naming a domain concept, use the term as defined in the relevant `CONTEXT.md`.

## Flag ADR conflicts

If your output contradicts an existing ADR, surface it explicitly rather than silently overriding it.