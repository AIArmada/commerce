<?php

declare(strict_types=1);

use AIArmada\Affiliates\Features\Support\Api\SupportTicketApi;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Features\Support\Models\AffiliateSupportMessage;
use AIArmada\Affiliates\Features\Support\Models\AffiliateSupportTicket;
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
    test('support API creates the initial message and reopens a replied ticket', function (): void {
        $api = app(SupportTicketApi::class);
        $id = $api->open((string) $this->affiliate->getKey(), 'Question', 'general', 'medium', 'Initial message', 'closed');

        expect($api->replyAsAffiliate((string) $this->affiliate->getKey(), $id, 'Follow-up'))->toBeTrue()
            ->and($api->replyAsAffiliate('wrong-affiliate', $id, 'Not authorized'))->toBeFalse();

        $ticket = AffiliateSupportTicket::query()->findOrFail($id);

        expect($ticket->status)->toBe('open')
            ->and($ticket->messages()->count())->toBe(2);
    });

    test('support API rolls back the ticket if its first message cannot be saved', function (): void {
        $event = 'eloquent.creating: ' . AffiliateSupportMessage::class;
        \Illuminate\Support\Facades\Event::listen($event, static function (): void {
            throw new RuntimeException('message unavailable');
        });

        try {
            expect(fn () => app(SupportTicketApi::class)->open(
                (string) $this->affiliate->getKey(), 'Atomic ticket', 'general', 'medium', 'Initial message',
            ))->toThrow(RuntimeException::class, 'message unavailable');
        } finally {
            \Illuminate\Support\Facades\Event::forget($event);
        }

        expect(AffiliateSupportTicket::query()->where('subject', 'Atomic ticket')->exists())->toBeFalse();
    });
});
