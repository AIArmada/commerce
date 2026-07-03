<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Events\Actions\BatchCreateOccurrencesAction;
use AIArmada\Events\Models\Event;
use Carbon\CarbonImmutable;

it('falls back to the template code when an occurrence code is blank', function (): void {
    OwnerContext::withOwner(null, function (): void {
        $event = Event::factory()->create();

        $created = app(BatchCreateOccurrencesAction::class)->handle(
            $event,
            [
                [
                    'title' => 'Day 1',
                    'starts_at' => CarbonImmutable::parse('2026-07-01 09:00:00'),
                    'ends_at' => CarbonImmutable::parse('2026-07-01 11:00:00'),
                    'timezone' => 'UTC',
                    'status' => 'scheduled',
                    'visibility' => 'public',
                    'delivery_mode' => 'in_person',
                    'code' => '',
                ],
            ],
            [
                'code' => 'vip-default',
                'name' => 'VIP',
                'price' => 2500,
                'currency' => 'MYR',
                'quota' => 50,
            ],
        );

        $ticketType = $created->firstOrFail()
            ->ticketTypes()
            ->firstOrFail();

        expect($ticketType->code)->toBe('vip-default');
    });
});

it('preserves template ticket type codes when batching occurrences', function (): void {
    OwnerContext::withOwner(null, function (): void {
        $event = Event::factory()->create();

        $created = app(BatchCreateOccurrencesAction::class)->handle(
            $event,
            [
                [
                    'title' => 'Day 1',
                    'starts_at' => CarbonImmutable::parse('2026-07-01 09:00:00'),
                    'ends_at' => CarbonImmutable::parse('2026-07-01 11:00:00'),
                    'timezone' => 'UTC',
                    'status' => 'scheduled',
                    'visibility' => 'public',
                    'delivery_mode' => 'in_person',
                ],
                [
                    'title' => 'Day 2',
                    'starts_at' => CarbonImmutable::parse('2026-07-02 09:00:00'),
                    'ends_at' => CarbonImmutable::parse('2026-07-02 11:00:00'),
                    'timezone' => 'UTC',
                    'status' => 'scheduled',
                    'visibility' => 'public',
                    'delivery_mode' => 'in_person',
                    'code' => 'day-two-explicit',
                ],
            ],
            [
                'code' => 'vip-default',
                'name' => 'VIP',
                'price' => 2500,
                'currency' => 'MYR',
                'quota' => 50,
            ],
        );

        expect($created)->toHaveCount(2);

        $firstTicketType = $created->firstOrFail()
            ->ticketTypes()
            ->firstOrFail();

        $secondOccurrence = $created->last();

        expect($secondOccurrence)->not->toBeNull();

        $secondTicketType = $secondOccurrence
            ?->ticketTypes()
            ->firstOrFail();

        expect($firstTicketType->code)->toBe('vip-default')
            ->and($secondTicketType->code)->toBe('day-two-explicit');
    });
});
