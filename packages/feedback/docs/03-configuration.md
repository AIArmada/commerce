---
title: Configuration
---

# Configuration

## Database

```php
'database' => [
    'table_prefix' => '',
    'tables' => [
        'forms' => 'feedback_forms',
        'sections' => 'feedback_sections',
        'questions' => 'feedback_questions',
        'question_options' => 'feedback_question_options',
        'responses' => 'feedback_responses',
        'answers' => 'feedback_answers',
        'invitations' => 'feedback_invitations',
        'templates' => 'feedback_templates',
        'testimonials' => 'feedback_testimonials',
    ],
],
```

## Owner scoping

```php
'owner' => [
    'enabled' => true,
    'auto_assign_on_create' => true,
        'include_global' => false,
],
```

## Defaults

```php
'defaults' => [
    'invitation_expiry_days' => 14,
],
```

## Feature toggles

```php
'features' => [
    'testimonials' => true,
],
```

## Security

```php
'security' => [
    'invitation_rate_limit' => [
        'max_attempts' => 60,
        'decay_seconds' => 60,
    ],
],
```

Invitation tokens are generated with cryptographically secure random bytes. Only
their SHA-256 hashes are persisted, and token resolution is rate limited by the
hashed token.

## Analytics

```php
'analytics' => [
    'dashboard_cache_ttl' => 30,
],
```

## HTTP routes

```php
'http' => [
    'route_prefix' => 'feedback',
],
```
