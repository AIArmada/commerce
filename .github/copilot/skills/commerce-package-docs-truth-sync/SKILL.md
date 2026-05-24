---
name: commerce-package-docs-truth-sync
description: >-
  Audits and updates AIArmada Commerce package docs for truth and consistency. Activates when
  normalizing package docs, fixing stale package documentation, updating installation or config
  references, adding AI-facing package context, cleaning up numbering drift, or when the user asks
  to audit docs, align docs with code, or prepare Commerce docs for AI retrieval.
---

# Commerce Package Docs Truth Sync

## When to Apply

Activate this skill when:

- package docs need a truth audit,
- a public package surface changed and docs must catch up,
- installation or config steps might be stale,
- package docs need normalization for AI retrieval,
- numbering or filename drift is hurting navigation.

## Read First

1. `docs/index.md`
2. `CONTEXT.md`
3. `docs/ai/package-manifests.json`
4. the target package docs
5. the target package config, composer manifest, and source tree

## Workflow

1. Audit truth first:
   - ownership boundary,
   - install steps,
   - config keys and defaults,
   - public API examples,
   - owner-scoping semantics.
2. Normalize structure second:
   - overview,
   - installation,
   - configuration,
   - usage,
   - troubleshooting.
3. Update package indexes and root indexes when canonical docs paths change.
4. Retire legacy duplicates only after the replacement docs exist.
5. Keep package docs concise enough for retrieval and explicit enough for ownership decisions.

## Expected Output

When you use this skill, identify:

- blocker or major doc drift,
- the minimum doc files that must change,
- any canonical links or indexes that must be updated,
- whether a legacy duplicate file can be retired safely.

## Common Pitfalls

- Polishing wording before fixing wrong ownership or wrong config keys
- Leaving root indexes pointing at retired files
- Treating archived docs as current implementation references
- Adding AI summaries that disagree with package docs instead of deriving from them
