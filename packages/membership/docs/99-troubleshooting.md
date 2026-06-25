---
title: Membership Troubleshooting
---

## Role Is Not Scoped to the Subject

Enable Spatie teams and `membership.features.team_scoped_roles`.

## Invitation Cannot Be Accepted

Confirm the invitation is pending, unexpired, and its normalized email matches the accepting user's email.

## Pivot Table Is Missing

Generate one for the subject model:

```bash
php artisan membership:make-pivot "App\Models\Team"
```
