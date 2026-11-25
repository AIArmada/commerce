<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Support\Actions;

use AIArmada\Affiliates\Features\Support\Models\AffiliateSupportMessage;
use AIArmada\Affiliates\Features\Support\Models\AffiliateSupportTicket;
use AIArmada\Affiliates\Models\Affiliate;

final readonly class OpenSupportTicket
{
    public function execute(string $affiliateId, string $subject, string $category, string $priority, ?string $message, string $status): string
    {
        TicketInput::validate($subject, $category, $priority, $status);
        if ($message !== null) {
            TicketInput::message($message);
        }

        return (new AffiliateSupportTicket)->getConnection()->transaction(function () use ($affiliateId, $subject, $category, $priority, $message, $status): string {
            Affiliate::query()->whereKey($affiliateId)->firstOrFail(['id']);
            $ticket = AffiliateSupportTicket::create([
                'affiliate_id' => $affiliateId,
                'subject' => $subject,
                'category' => $category,
                'priority' => $priority,
                'status' => $status,
            ]);

            if ($message !== null) {
                AffiliateSupportMessage::create([
                    'ticket_id' => $ticket->getKey(),
                    'affiliate_id' => $affiliateId,
                    'message' => mb_trim($message),
                    'is_staff_reply' => false,
                ]);
            }

            return (string) $ticket->getKey();
        });
    }
}
