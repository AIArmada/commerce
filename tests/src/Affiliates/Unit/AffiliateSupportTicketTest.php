<?php

declare(strict_types=1);

use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateSupportMessage;
use AIArmada\Affiliates\Models\AffiliateSupportTicket;
use AIArmada\Affiliates\States\Active;

describe('AffiliateSupportTicket Model', function (): void {
    beforeEach(function (): void {
        $this->affiliate = Affiliate::create([
            'code' => 'SUPPORT' . uniqid(),
            'name' => 'Support Test Affiliate',
            'status' => Active::class,
            'commission_type' => 'percentage',
            'commission_rate' => 1000,
            'currency' => 'USD',
        ]);
    });

    test('messages are ordered by created_at', function (): void {
        $ticket = AffiliateSupportTicket::create([
            'affiliate_id' => $this->affiliate->id,
            'subject' => 'Message ordering test',
            'category' => 'general',
            'priority' => 'normal',
            'status' => 'open',
        ]);

        $firstMessage = AffiliateSupportMessage::create([
            'ticket_id' => $ticket->id,
            'affiliate_id' => $this->affiliate->id,
            'message' => 'First message',
            'is_staff_reply' => false,
            'created_at' => now()->subHour(),
        ]);

        $secondMessage = AffiliateSupportMessage::create([
            'ticket_id' => $ticket->id,
            'staff_id' => 'agent@example.com',
            'message' => 'Second message',
            'is_staff_reply' => true,
            'created_at' => now(),
        ]);

        $messages = $ticket->messages;
        expect($messages->first()->id)->toBe($firstMessage->id);
        expect($messages->last()->id)->toBe($secondMessage->id);
    });

    test('can have different categories', function (): void {
        $technical = AffiliateSupportTicket::create([
            'affiliate_id' => $this->affiliate->id,
            'subject' => 'Technical issue',
            'category' => 'technical',
            'priority' => 'normal',
            'status' => 'open',
        ]);

        $billing = AffiliateSupportTicket::create([
            'affiliate_id' => $this->affiliate->id,
            'subject' => 'Billing question',
            'category' => 'billing',
            'priority' => 'normal',
            'status' => 'open',
        ]);

        expect($technical->category)->toBe('technical');
        expect($billing->category)->toBe('billing');
    });
});
