<?php

declare(strict_types=1);

namespace AIArmada\Chip\Features\Payouts\Actions;

use AIArmada\Chip\Features\Payouts\Models\SendWebhook;
use AIArmada\Chip\Services\ChipSendService;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Illuminate\Auth\Access\AuthorizationException;

final readonly class SyncSendWebhook
{
    public function __construct(private ChipSendService $send) {}

    public function handle(int $id): SendWebhook
    {
        $record = SendWebhook::query()->forOwner(OwnerContext::CURRENT, false)->findOrFail($id);
        $snapshot = $this->send->getSendWebhook($id);

        if ($snapshot->id !== $id) {
            throw new AuthorizationException('CHIP response does not match the requested record.');
        }

        $record->fill($snapshot->toArray())->save();

        return $record;
    }
}
