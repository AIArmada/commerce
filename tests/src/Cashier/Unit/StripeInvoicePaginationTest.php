<?php

declare(strict_types=1);

use AIArmada\Cashier\Contracts\BillableContract;
use AIArmada\Cashier\Drivers\StripeGateway;
use AIArmada\Commerce\Tests\Cashier\CashierTestCase;
use AIArmada\Commerce\Tests\Cashier\Fixtures\User;
use Illuminate\Support\Collection;
use Laravel\Cashier\Invoice;
use Stripe\Invoice as StripeInvoice;

uses(CashierTestCase::class);

it('loads more than 100 paid invoices using bounded Stripe cursor pages', function (): void {
    config()->set('commerce-support.owner.enabled', false);

    $user = new class extends User implements BillableContract
    {
        /** @var list<array<string, mixed>> */
        public array $requests = [];

        public function invoices(bool $includePending = false, array $parameters = []): Collection
        {
            $this->requests[] = $parameters;
            $offset = isset($parameters['starting_after'])
                ? (int) mb_substr($parameters['starting_after'], 3)
                : 0;

            $invoices = [];

            for ($i = $offset + 1; $i <= min(250, $offset + $parameters['limit']); $i++) {
                $invoices[] = new Invoice($this, StripeInvoice::constructFrom([
                    'id' => 'in_' . $i,
                    'customer' => $this->stripe_id,
                    'status' => 'paid',
                ]));
            }

            return collect($invoices);
        }
    };

    $user->forceFill(['stripe_id' => 'cus_pagination']);

    $invoices = (new StripeGateway)->invoices($user, ['limit' => 201]);

    expect($invoices)->toHaveCount(201)
        ->and($invoices->first()->id())->toBe('in_1')
        ->and($invoices->last()->id())->toBe('in_201')
        ->and($user->requests)->toBe([
            ['limit' => 100, 'status' => 'paid'],
            ['limit' => 100, 'status' => 'paid', 'starting_after' => 'in_100'],
            ['limit' => 1, 'status' => 'paid', 'starting_after' => 'in_200'],
        ]);
});
