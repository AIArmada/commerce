<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use AIArmada\CashierChip\Cashier;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Products\Models\Product;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;

class BillingController extends Controller
{
    /**
     * Single product checkout with Chip (one-time payment).
     */
    public function singleChipCheckout(string $slug): View
    {
        $owner = OwnerContext::resolve();

        if ($owner === null) {
            abort(404);
        }

        $product = Product::query()
            ->where('owner_type', $owner->getMorphClass())
            ->where('owner_id', (string) $owner->getKey())
            ->where('slug', $slug)
            ->firstOrFail();

        return view('billing.single-chip', compact('product'));
    }

    /**
     * Process single Chip payment.
     */
    public function processSingleChip(Request $request): RedirectResponse
    {
        $request->validate([
            'chip_token' => 'required|string',
            'product_id' => ['required', 'string'],
        ]);

        $owner = OwnerContext::resolve();

        if ($owner === null) {
            abort(404);
        }

        $product = Product::query()
            ->where('owner_type', $owner->getMorphClass())
            ->where('owner_id', (string) $owner->getKey())
            ->whereKey((string) $request->product_id)
            ->firstOrFail();

        $user = Auth::user() ?? User::create([
            'name' => $request->name ?? 'Guest',
            'email' => $request->email,
            'password' => bcrypt(Str::random(12)),
        ]);

        // Create purchase with Chip
        $purchase = Cashier::chip()->createPurchase([
            'amount' => $product->price,
            'currency' => 'MYR',
            'token' => $request->chip_token,
            'description' => "Purchase: {$product->name}",
            'metadata' => [
                'product_id' => $product->id,
                'user_id' => $user->id,
            ],
        ]);

        return redirect()->route('checkout.success', $purchase->id)
            ->with('success', 'Payment successful!');
    }

    /**
     * Chip subscription checkout.
     */
    public function subscribeChip(string $plan): View
    {
        $plans = [
            'pro' => ['name' => 'Pro Monthly', 'price_id' => 'price_pro_monthly', 'amount' => 9900, 'billing_interval' => 'month'],
            'business' => ['name' => 'Business Annual', 'price_id' => 'price_business_yearly', 'amount' => 99900, 'billing_interval' => 'year'],
        ];

        $planData = $plans[$plan] ?? abort(404);

        return view('billing.subscribe-chip', compact('planData'));
    }

    /**
     * Process Chip subscription.
     */
    public function processSubscribeChip(Request $request): RedirectResponse
    {
        $request->validate([
            'plan' => 'required|in:pro,business',
        ]);

        $user = Auth::user();
        if (! $user) {
            abort(401, 'Authentication required');
        }

        $plan = (string) $request->input('plan', 'pro');

        $planData = [
            'pro' => ['price_id' => 'price_pro_monthly', 'amount' => 9900, 'billing_interval' => 'month'],
            'business' => ['price_id' => 'price_business_yearly', 'amount' => 99900, 'billing_interval' => 'year'],
        ][$plan] ?? abort(404);

        $subscriptionBuilder = $user->newSubscription('default')
            ->price([
                'price' => $planData['price_id'],
                'unit_amount' => $planData['amount'],
                'quantity' => 1,
            ]);

        $subscriptionBuilder = $planData['billing_interval'] === 'year'
            ? $subscriptionBuilder->yearly()
            : $subscriptionBuilder->monthly();

        $checkout = $subscriptionBuilder->checkout([
            'success_url' => route('billing.portal'),
            'cancel_url' => route('subscribe.chip', ['plan' => $plan]),
        ]);

        return $checkout->redirect();
    }

    /**
     * Billing portal for CHIP subscriptions.
     */
    public function portal(): RedirectResponse
    {
        $user = Auth::user();
        if (! $user) {
            return redirect('/login');
        }

        if ($user->subscribed('default')) {
            return redirect('/admin')
                ->with('success', 'Your CHIP subscription is active. Manage billing from the demo admin panel.');
        }

        return redirect()
            ->route('subscribe.chip', ['plan' => 'pro'])
            ->with('info', 'Choose a CHIP plan to start billing through the demo checkout flow.');
    }
}
