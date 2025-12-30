<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use AIArmada\CashierChip\Facades\CashierChip;
use AIArmada\Products\Models\Product;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class BillingController extends Controller
{
    /**
     * Single product checkout with Chip (one-time payment).
     */
    public function singleChipCheckout(string $slug): View
    {
        $product = Product::where('slug', $slug)->firstOrFail();

        return view('billing.single-chip', compact('product'));
    }

    /**
     * Process single Chip payment.
     */
    public function processSingleChip(Request $request): RedirectResponse
    {
        $request->validate([
            'chip_token' => 'required|string',
            'product_id' => ['required', Rule::exists((new Product)->getTable(), 'id')],
        ]);

        $product = Product::findOrFail($request->product_id);
        $user = Auth::user() ?? User::create([
            'name' => $request->name ?? 'Guest',
            'email' => $request->email,
            'password' => bcrypt(Str::random(12)),
        ]);

        // Create purchase with Chip
        $purchase = CashierChip::createPurchase([
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
            'pro' => ['name' => 'Pro Monthly', 'price_id' => 'price_pro_monthly', 'amount' => 9900],
            'business' => ['name' => 'Business Annual', 'price_id' => 'price_business_yearly', 'amount' => 99900],
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
            'chip_token' => 'required|string',
        ]);

        $user = Auth::user();
        if (! $user) {
            abort(401, 'Authentication required');
        }

        $plan = $request->plan ?? 'pro';

        $subscription = $user->newChipSubscription($plan, $request->chip_token)
            ->create();

        return redirect()->route('billing.portal')
            ->with('success', "Subscribed to {$plan} plan!");
    }

    /**
     * Stripe subscription checkout.
     */
    public function subscribeStripe(string $plan): View
    {
        $user = Auth::user();
        if (! $user) {
            abort(401);
        }

        $user->createOrRetrieveStripeCustomer();

        $plans = [
            'pro' => 'price_pro_monthly_stripe', // Assume price IDs
            'business' => 'price_business_yearly_stripe',
        ];

        $intent = $user->createSetupIntent();

        return view('billing.subscribe-stripe', [
            'intent' => $intent,
            'plan' => $plans[$plan] ?? abort(404),
        ]);
    }

    /**
     * Process Stripe subscription.
     */
    public function processSubscribeStripe(Request $request): RedirectResponse
    {
        $user = Auth::user();

        $subscription = $user->newSubscription('default', $request->plan)
            ->create($request->stripe_token);

        return redirect()->route('billing.portal')
            ->with('success', 'Stripe subscription created!');
    }

    /**
     * Billing portal (unified for Chip + Stripe).
     */
    public function portal(): RedirectResponse | View
    {
        $user = Auth::user();
        if (! $user) {
            return redirect('/login');
        }

        // CashierChip portal
        if ($user->hasChipSubscription()) {
            return redirect(CashierChip::portalUrl($user));
        }

        // Stripe portal
        $portalUrl = $user->billingPortalUrl();

        return redirect($portalUrl);
    }
}
