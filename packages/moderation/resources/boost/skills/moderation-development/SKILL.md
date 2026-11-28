---
name: moderation-development
description: Create owner-scoped blocks and moderation audit records using the moderation package.
---

# Moderation development

Resolve concrete actions from the container. Pass persisted target and actor models; the actions enforce owner isolation and configured actor types. Use `HasBlocks::block()` and `HasModerationActions::recordModerationAction()` for model-oriented entry points. Do not write moderation rows directly from controllers or UI adapters. Run the moderation Pest tests and package architecture checks after changes.
