# Future Enhancement Progress Tracker

## Overview

This document tracks implementation progress for the future features documented in `docs/future/*.md`.

## Quick Status

| Phase | Status | Progress |
|-------|--------|----------|
| Phase 1: Shield Parity | ⏳ Planned | 0% |
| Phase 2: Advanced Features | ⏳ Planned | 0% |
| Phase 3: Visual Tools | ⏳ Planned | 0% |
| Phase 4: Enterprise | ⏳ Planned | 0% |

---

## Phase 1: Shield Parity

### Entity Discovery Engine
**Status:** ⏳ Not Started

- [ ] `EntityDiscoveryService` base implementation
- [ ] Resource transformer
- [ ] Page transformer
- [ ] Widget transformer
- [ ] Discovery caching layer
- [ ] `authz:discover` command
- [ ] Multi-panel discovery
- [ ] Custom action detection
- [ ] Relation manager detection
- [ ] Tests (90%+ coverage)

### Setup Wizard
**Status:** ⏳ Not Started

- [ ] `authz:setup` command scaffold
- [ ] Environment detection (Spatie, Filament, guards)
- [ ] Interactive configuration prompts
- [ ] Database migration runner
- [ ] Role creation flow
- [ ] Permission generation integration
- [ ] Policy generation integration
- [ ] Super admin assignment
- [ ] Verification step
- [ ] Minimal mode (non-interactive)
- [ ] Tests

### Enforcement Traits
**Status:** ⏳ Not Started

- [ ] `HasPagePermissions` trait
- [ ] `HasWidgetPermissions` trait
- [ ] `HasResourcePermissions` trait
- [ ] `HasPanelPermissions` trait
- [ ] Team scope support
- [ ] Owner scope support
- [ ] Temporal grant integration
- [ ] Audit logging integration
- [ ] Debug mode (permission key display)
- [ ] Tests

---

## Phase 2: Advanced Features

### Advanced Policy Generator
**Status:** ⏳ Not Started

- [ ] `PolicyType` enum
- [ ] Basic policy stub
- [ ] Hierarchical policy stub
- [ ] Contextual policy stub
- [ ] Temporal policy stub
- [ ] ABAC policy stub
- [ ] Composite policy stub
- [ ] Method stubs (single/multi param)
- [ ] `PolicyGeneratorService`
- [ ] `authz:policies` command
- [ ] Interactive type selection
- [ ] Dry-run preview
- [ ] Custom stubs support
- [ ] Tests

### Code Manipulation Engine
**Status:** ⏳ Not Started

- [ ] `CodeManipulator` service
- [ ] PhpParser integration
- [ ] `AddTraitVisitor`
- [ ] `AddMethodVisitor`
- [ ] `SetPropertyVisitor`
- [ ] `AppendToArrayVisitor`
- [ ] String-based fallbacks (Stringer-compatible)
- [ ] History/undo support
- [ ] Diff preview
- [ ] `authz:install-trait` command
- [ ] Tests

---

## Phase 3: Visual Tools

### Visual Policy Designer
**Status:** ⏳ Not Started

- [ ] Designer page scaffold
- [ ] Condition templates (10+)
  - [ ] Role-based
  - [ ] Permission-based
  - [ ] Team-based
  - [ ] Time-based
  - [ ] IP-based
  - [ ] Resource attribute-based
  - [ ] Ownership-based
- [ ] Drag-and-drop UI (Livewire)
- [ ] Condition grouping (AND/OR)
- [ ] Effect selection (Allow/Deny)
- [ ] Policy compilation
- [ ] Test/simulation panel
- [ ] JSON preview
- [ ] Code export
- [ ] Policy templates library
- [ ] Blade views
- [ ] Tests

### Real-time Dashboard
**Status:** ⏳ Not Started

- [ ] Dashboard page scaffold
- [ ] Permission stats widget
- [ ] Recent activity widget
- [ ] Live event stream (WebSocket)
- [ ] Filter by user/permission
- [ ] Denial-only mode
- [ ] Anomaly detection widget
- [ ] Permission usage heatmap
- [ ] Hourly breakdown charts
- [ ] WebSocket broadcasting setup
- [ ] Fallback to polling
- [ ] Tests

---

## Phase 4: Enterprise

### Identity Provider Integration
**Status:** ⏳ Not Started

- [ ] `IdentityProviderSync` service
- [ ] LDAP connection support
- [ ] LDAP group-to-role mapping
- [ ] SAML assertion handling
- [ ] SAML role mapping
- [ ] Scheduled sync job
- [ ] Configuration options
- [ ] Tests

### Compliance Automation
**Status:** ⏳ Not Started

- [ ] `ComplianceReportGenerator` service
- [ ] SOC2 access review report
- [ ] Segregation of duties analysis
- [ ] GDPR data access report
- [ ] Compliance score calculation
- [ ] Compliance dashboard widget
- [ ] PDF/Excel export
- [ ] Tests

### Permission Versioning
**Status:** ⏳ Not Started

- [ ] `PermissionSnapshot` model
- [ ] `PermissionVersioning` service
- [ ] Snapshot creation
- [ ] Snapshot comparison (diff)
- [ ] Rollback preview (dry-run)
- [ ] Rollback execution
- [ ] `authz:snapshot` commands
- [ ] Tests

### Approval Workflows
**Status:** ⏳ Not Started

- [ ] `PermissionRequest` model
- [ ] Request creation flow
- [ ] Approval flow
- [ ] Denial flow
- [ ] Temporal (expiring) requests
- [ ] Email notifications
- [ ] Approval UI (Resource)
- [ ] Tests

### Delegation System
**Status:** ⏳ Not Started

- [ ] `Delegation` model
- [ ] `DelegationService`
- [ ] Delegation permission checks
- [ ] Delegation creation
- [ ] Delegation revocation
- [ ] Cascade revocation
- [ ] Delegation UI
- [ ] Tests

---

## Changelog

### [Unreleased]

#### Added
- Initial future documentation (10 files)

---

## Contributing

When implementing a feature:

1. Check off tasks as completed
2. Update the status emoji
3. Add changelog entry
4. Update test coverage
5. Submit PR referencing this document
