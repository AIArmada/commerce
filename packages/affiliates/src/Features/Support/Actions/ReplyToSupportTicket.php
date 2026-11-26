<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Support\Actions;

use AIArmada\Affiliates\Features\Support\Models\AffiliateSupportMessage;
use AIArmada\Affiliates\Features\Support\Models\AffiliateSupportTicket;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;

final readonly class ReplyToSupportTicket
{
    public function asAffiliate(string $affiliateId, string $ticketId, string $message): bool
    {
        TicketInput::message($message);

        return (new AffiliateSupportTicket)->getConnection()->transaction(function () use ($affiliateId, $ticketId, $message): bool {
            $ticket = AffiliateSupportTicket::query()->whereKey($ticketId)->where('affiliate_id', $affiliateId)->lockForUpdate()->first();

            if ($ticket === null) {
                return false;
            }

            AffiliateSupportMessage::create([
                'ticket_id' => $ticketId,
                'affiliate_id' => $affiliateId,
                'message' => mb_trim($message),
                'is_staff_reply' => false,
            ]);
            $ticket->update(['status' => 'open']);

            return true;
        });
    }

    public function asStaff(string $ticketId, string $staffId, string $message): string
    {
        TicketInput::message($message);

        if ($staffId === '' || (string) auth()->id() !== $staffId) {
            throw new AuthorizationException('Invalid staff identity.');
        }

        return (new AffiliateSupportTicket)->getConnection()->transaction(function () use ($ticketId, $staffId, $message): string {
            $ticket = AffiliateSupportTicket::query()->whereKey($ticketId)->lockForUpdate()->firstOrFail();
            Gate::authorize('update', $ticket);

            return (string) AffiliateSupportMessage::create([
                'ticket_id' => $ticketId,
                'staff_id' => $staffId,
                'message' => mb_trim($message),
                'is_staff_reply' => true,
            ])->getKey();
        });
    }
}
