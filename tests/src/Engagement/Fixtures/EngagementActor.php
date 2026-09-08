<?php

declare(strict_types=1);

namespace AIArmada\Engagement\Tests\Fixtures;

use AIArmada\Engagement\Contracts\CanInteract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

final class EngagementActor extends Model implements CanInteract
{
    use Notifiable;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['name', 'email', 'password'];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        if (! isset($this->attributes[$this->getKeyName()])) {
            $this->setAttribute($this->getKeyName(), (string) Str::uuid());
        }
    }

    public function getTable(): string
    {
        return 'users';
    }

    public function getMorphClass(): string
    {
        return static::class;
    }

    public function interactionDisplayName(): string
    {
        return (string) ($this->getAttribute('name') ?? 'Engagement actor');
    }

    public function interactionNotificationRoute(?string $channel = null): mixed
    {
        return null;
    }

    public function notify(mixed $instance): void
    {
        if (! $instance instanceof Notification) {
            return;
        }
    }
}
