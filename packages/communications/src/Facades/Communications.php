<?php

declare(strict_types=1);

namespace AIArmada\Communications\Facades;

use AIArmada\Communications\Contracts\CommunicationManager;
use AIArmada\Communications\Testing\FakeCommunicationManager;
use Illuminate\Support\Facades\Facade;

/**
 * @method static \AIArmada\Communications\Models\Communication notify(mixed $notifiable, \Illuminate\Notifications\Notification $notification, ?\AIArmada\Communications\Data\CommunicationContextData $context = null)
 * @method static \AIArmada\Communications\Models\Communication|null recordNative(mixed $notifiable, \Illuminate\Notifications\Notification $notification, string $channel)
 * @method static \AIArmada\Communications\Testing\FakeCommunicationManager fake()
 * @method static void assertSent(?\Closure $callback = null, ?int $count = null)
 * @method static void assertNotSent(\Closure $callback)
 * @method static void assertNothingSent()
 * @method static void assertSentTimes(int $times)
 *
 * @see CommunicationManager
 */
final class Communications extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return CommunicationManager::class;
    }

    public static function fake(): FakeCommunicationManager
    {
        $fake = new FakeCommunicationManager;

        static::swap($fake);

        return $fake;
    }
}
