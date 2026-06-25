---
title: Configuration
---

# Configuration

## Database

```php
'database' => [
    'table_prefix' => '',
    'json_column_type' => 'jsonb',
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
    'include_global_templates' => false,
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

## HTTP routes

```php
'http' => [
    'route_prefix' => 'feedback',
],
```
