<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Support\Queries;

use AIArmada\Affiliates\Features\Support\Models\AffiliateSupportTicket;
use AIArmada\Affiliates\Features\Support\Models\AffiliateSupportMessage;

final class SupportTicketQuery
{
    /** @return list<array<string, mixed>> */
    public function forAffiliate(string $affiliateId): array
    {
        return AffiliateSupportTicket::query()
            ->where('affiliate_id', $affiliateId)
            ->with('messages')
            ->latest('created_at')
            ->limit(100)
            ->get()
            ->map(static fn (AffiliateSupportTicket $ticket): array => [
                'id' => (string) $ticket->getKey(),
                'subject' => $ticket->subject,
                'category' => $ticket->category,
                'priority' => $ticket->priority,
                'status' => $ticket->status,
                'messages' => $ticket->messages->map(static fn (AffiliateSupportMessage $message): array => [
                    'id' => (string) $message->getKey(),
                    'message' => $message->message,
                    'is_staff_reply' => $message->is_staff_reply,
                    'created_at' => $message->created_at?->toDateTimeString(),
                ])->all(),
            ])->values()->all();
    }
}
