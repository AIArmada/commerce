---
name: references-development
description: Manage bibliographic references, typed hierarchies, contributors, and citation parts.
---

# References development

- Use AIArmada\References\Models\Reference for owner-scoped reads and model validation.
- Resolve AIArmada\References\Actions\SyncReferenceContributors and TransitionReferenceStatus through the container and call handle().
- Reload retained references after publication state changes; direct saves enforce the same publication timestamp rules as the status action.
- Only works own authors; editions and parts inherit authors from the work.
- Never disable owner scope for consumer writes. Use explicit global owner context for global changes.
- Call the model's delete() for transactional subtree cleanup and events.
- Verify changed behavior with tests/src/References and package architecture checks.
