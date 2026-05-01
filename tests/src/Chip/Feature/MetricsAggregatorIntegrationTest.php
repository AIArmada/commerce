<?php

declare(strict_types=1);

use AIArmada\Chip\Models\DailyMetric;
use AIArmada\Chip\Models\Purchase;
use AIArmada\Chip\Services\MetricsAggregator;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

describe('MetricsAggregator', function (): void {
    beforeEach(function (): void {
        $this->aggregator = new MetricsAggregator;
    });

    describe('instantiation', function (): void {
        it('can be instantiated', function (): void {
            expect($this->aggregator)->toBeInstanceOf(MetricsAggregator::class);
        });
    });

    describe('method signatures', function (): void {
        it('aggregateForDate accepts Carbon date', function (): void {
            $reflection = new ReflectionMethod($this->aggregator, 'aggregateForDate');
            $params = $reflection->getParameters();

            expect($params)->toHaveCount(1);
            expect($params[0]->getType()->getName())->toBe(CarbonImmutable::class);
        });

        it('aggregateTotals accepts Carbon date', function (): void {
            $reflection = new ReflectionMethod($this->aggregator, 'aggregateTotals');
            $params = $reflection->getParameters();

            expect($params)->toHaveCount(1);
            expect($params[0]->getType()->getName())->toBe(CarbonImmutable::class);
        });

        it('backfill accepts start and end dates', function (): void {
            $reflection = new ReflectionMethod($this->aggregator, 'backfill');
            $params = $reflection->getParameters();

            expect($params)->toHaveCount(2);
            expect($params[0]->getName())->toBe('startDate');
            expect($params[1]->getName())->toBe('endDate');
        });

        it('backfill returns int', function (): void {
            $reflection = new ReflectionMethod($this->aggregator, 'backfill');
            $returnType = $reflection->getReturnType();

            expect($returnType->getName())->toBe('int');
        });

        it('getFailureBreakdown is protected', function (): void {
            $reflection = new ReflectionMethod($this->aggregator, 'getFailureBreakdown');

            expect($reflection->isProtected())->toBeTrue();
        });

        it('getFailureBreakdown accepts start, end dates and payment method', function (): void {
            $reflection = new ReflectionMethod($this->aggregator, 'getFailureBreakdown');
            $params = $reflection->getParameters();

            expect($params)->toHaveCount(3);
            expect($params[0]->getName())->toBe('startDate');
            expect($params[1]->getName())->toBe('endDate');
            expect($params[2]->getName())->toBe('paymentMethod');
        });
    });

    describe('aggregateTotals execution', function (): void {
        it('does not throw when called with date having no metrics', function (): void {
            $date = CarbonImmutable::now()->subDays(50);

            // This should not throw even with no input metrics
            $exception = null;

            try {
                $this->aggregator->aggregateTotals($date);
            } catch (Throwable $e) {
                $exception = $e;
            }

            expect($exception)->toBeNull();
        });

        it('does not throw when called with recent date', function (): void {
            $date = CarbonImmutable::now()->subDays(1);

            $exception = null;

            try {
                $this->aggregator->aggregateTotals($date);
            } catch (Throwable $e) {
                $exception = $e;
            }

            expect($exception)->toBeNull();
        });

        it('stores separate daily metric rows for different owners on the same date', function (): void {
            Schema::dropIfExists('tenants');

            Schema::create('tenants', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('name');
                $table->timestamps();
            });

            $ownerA = new class extends Model
            {
                protected $table = 'tenants';

                public $incrementing = false;

                protected $keyType = 'string';

                protected $guarded = [];
            };

            $ownerB = new class extends Model
            {
                protected $table = 'tenants';

                public $incrementing = false;

                protected $keyType = 'string';

                protected $guarded = [];
            };

            $ownerA->id = 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa';
            $ownerA->name = 'Owner A';
            $ownerA->save();

            $ownerB->id = 'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb';
            $ownerB->name = 'Owner B';
            $ownerB->save();

            config()->set('chip.owner.enabled', true);
            config()->set('chip.owner.auto_assign_on_create', true);

            $date = CarbonImmutable::parse('2026-04-20');
            $timestamp = $date->startOfDay()->timestamp;

            OwnerContext::withOwner($ownerA, function () use ($timestamp): void {
                Purchase::create([
                    'id' => '11111111-1111-1111-1111-111111111111',
                    'type' => 'purchase',
                    'created_on' => $timestamp,
                    'updated_on' => $timestamp,
                    'client' => ['email' => 'a@example.com'],
                    'purchase' => ['amount' => 1000, 'currency' => 'MYR'],
                    'brand_id' => 'aaaaaaaa-0000-0000-0000-000000000000',
                    'issuer_details' => [],
                    'transaction_data' => [],
                    'status_history' => [],
                    'status' => 'paid',
                    'payment_method' => 'fpx',
                    'total_minor' => 1000,
                    'created_at' => CarbonImmutable::createFromTimestampUTC($timestamp),
                    'updated_at' => CarbonImmutable::createFromTimestampUTC($timestamp),
                ]);
            });

            OwnerContext::withOwner($ownerB, function () use ($timestamp): void {
                Purchase::create([
                    'id' => '22222222-2222-2222-2222-222222222222',
                    'type' => 'purchase',
                    'created_on' => $timestamp,
                    'updated_on' => $timestamp,
                    'client' => ['email' => 'b@example.com'],
                    'purchase' => ['amount' => 2000, 'currency' => 'MYR'],
                    'brand_id' => 'bbbbbbbb-0000-0000-0000-000000000000',
                    'issuer_details' => [],
                    'transaction_data' => [],
                    'status_history' => [],
                    'status' => 'paid',
                    'payment_method' => 'fpx',
                    'total_minor' => 2000,
                    'created_at' => CarbonImmutable::createFromTimestampUTC($timestamp),
                    'updated_at' => CarbonImmutable::createFromTimestampUTC($timestamp),
                ]);
            });

            OwnerContext::withOwner($ownerA, fn () => $this->aggregator->aggregateForDate($date));
            OwnerContext::withOwner($ownerB, fn () => $this->aggregator->aggregateForDate($date));

            $metrics = DailyMetric::query()
                ->withoutOwnerScope()
                ->where('date', $date->toDateString())
                ->where('payment_method', 'fpx')
                ->orderBy('owner_id')
                ->get();

            expect($metrics)->toHaveCount(2)
                ->and($metrics->pluck('owner_id')->all())
                ->toBe([
                    'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa',
                    'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb',
                ]);
        });
    });
});
