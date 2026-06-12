---
title: Configuration
---

# Configuration

## Navigation

```php
'navigation' => [
    'group' => 'Feedback',
    'icon' => 'heroicon-o-chat-bubble-left-right',
    'sort' => 70,
],
```

## Resource toggles

```php
'resources' => [
    'enabled' => [
        'feedback_form' => true,
        'feedback_response' => true,
        'feedback_invitation' => true,
        'feedback_template' => true,
        'feedback_testimonial' => true,
    ],
],
```

## Feature toggles

```php
'features' => [
    'forms' => true,
    'responses' => true,
    'invitations' => true,
    'templates' => true,
    'testimonials' => true,
    'analytics_dashboard' => true,
    'exports' => true,
],
```

## Table defaults

```php
'tables' => [
    'default_pagination' => 25,
],
```
