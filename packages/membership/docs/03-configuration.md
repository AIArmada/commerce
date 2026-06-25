---
title: Membership Configuration
---

## Main Settings

```php
return [
    'database' => [
        'tables' => [
            'applications' => 'membership_applications',
            'invitations' => 'membership_invitations',
        ],
        'json_column_type' => 'jsonb',
    ],
    'invitations' => [
        'token_length' => 64,
        'hash_tokens' => true,
        'default_expiry_days' => 14,
    ],
    'pivot' => [
        'table_suffix' => '_members',
    ],
    'features' => [
        'team_scoped_roles' => true,
    ],
];
```

Keep `hash_tokens` enabled in production. The plaintext token is available only on `MembershipInvitationSent`.
