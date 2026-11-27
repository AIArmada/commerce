# Moderation

Use `AIArmada\Moderation\Actions\BlockEntityAction` and `RecordModerationAction` for writes. Use `HasBlocks` and `HasModerationActions` on persisted target models. Set explicit owner context before moderation; never accept arbitrary actor morph types from requests. Configure `moderation.actors.allowed_types`. Use `Block::query()->active()` for date-aware enforcement.
