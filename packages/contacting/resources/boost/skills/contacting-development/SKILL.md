---
name: contacting-development
description: Manage normalized, owner-scoped contacts, social profiles and append-only snapshots.
---

# Contacting development

Use `Features/ContactMethods/Actions` and `Features/SocialProfiles/Actions` for mutations, and `Features/Snapshots/Actions/CreateContactSnapshotAction` for immutable contact history. Resolve actions via the container. Do not directly demote primary records or bypass owner validation. The package has independent feature manifests and module registrations. Run architecture checks and contacting/Filament contacting regression tests.
