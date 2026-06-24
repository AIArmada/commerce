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

If the moderated model uses `HasOwner`, make sure the current owner context is set before creating moderation records. The package validates owner-scoped models through `commerce-support` guards and still allows global models when that is intentional.
