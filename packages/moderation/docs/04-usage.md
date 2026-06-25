---
title: Usage
---

# Usage

## Blocking a model

```php
use AIArmada\Moderation\Traits\HasBlocks;
use Illuminate\Database\Eloquent\Model;

final class Comment extends Model
{
    use HasBlocks;
}

$comment->block(
    reason: 'spam',
    notes: 'Repeated link drops',
    blockedById: (string) $admin->getKey(),
    blockedByType: $admin::class,
);
```

`block()` resolves the actor model safely when an ID and class name are provided. If you do not pass an actor, the block is still recorded.

## Recording a moderation action

```php
use AIArmada\Moderation\Enums\ModerationActionType;
use AIArmada\Moderation\Traits\HasModerationActions;
use Illuminate\Database\Eloquent\Model;

final class Comment extends Model
{
    use HasModerationActions;
}

$comment->recordModerationAction(
    type: ModerationActionType::Warn,
    reason: 'Review required',
);
```

## Working with queries

```php
use AIArmada\Moderation\Models\Block;

$activeBlocks = Block::query()->active()->get();
$expiredBlocks = Block::query()->expired()->get();
```

```php
use Illuminate\Database\Eloquent\Model;

final class Comment extends Model
{
    use HasBlocks;
}

$visibleComments = Comment::query()->whereNotBlocked()->get();
```

## Owner-scoped models

When owner mode is enabled, set an owner context before creating or querying moderation records. The package validates owner-scoped targets and actors through `commerce-support` guards; use explicit global context for intentional global moderation.
