<x-shop-layout title="Demo Payment Gateway">
    <div class="max-w-4xl mx-auto px-4 py-10 sm:px-6 lg:px-8">
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="bg-gradient-to-r from-slate-900 via-slate-800 to-slate-900 text-white px-8 py-6">
                <p class="text-sm uppercase tracking-[0.2em] text-sky-200">Development payment surface</p>
                <h1 class="text-3xl font-bold mt-2">Demo Payment Gateway</h1>
                <p class="text-sm text-slate-200 mt-2">
                    This local payment screen exists so the demo app can exercise the full checkout redirect and callback flow even when live gateway credentials are unavailable.
                </p>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 p-8">
                <div class="space-y-5">
                    <div class="rounded-xl border border-slate-200 bg-slate-50 p-5">
                        <h2 class="text-lg font-semibold text-slate-900">Checkout session</h2>
                        <dl class="mt-4 space-y-3 text-sm">
                            <div class="flex justify-between gap-4">
                                <dt class="text-slate-500">Session ID</dt>
                                <dd class="text-slate-900 font-mono text-right break-all">{{ $checkoutSession->id }}</dd>
                            </div>
                            <div class="flex justify-between gap-4">
                                <dt class="text-slate-500">Payment ID</dt>
                                <dd class="text-slate-900 font-mono text-right break-all">{{ $checkoutSession->payment_id }}</dd>
                            </div>
                            <div class="flex justify-between gap-4">
                                <dt class="text-slate-500">Gateway</dt>
                                <dd class="text-slate-900 text-right uppercase">{{ $checkoutSession->selected_payment_gateway ?? 'demo' }}</dd>
                            </div>
                            <div class="flex justify-between gap-4">
                                <dt class="text-slate-500">Requested method</dt>
                                <dd class="text-slate-900 text-right uppercase">{{ $requestedPaymentMethod }}</dd>
                            </div>
                            <div class="flex justify-between gap-4">
                                <dt class="text-slate-500">Amount</dt>
                                <dd class="text-slate-900 text-right font-semibold">RM {{ number_format($checkoutSession->grand_total / 100, 2) }}</dd>
                            </div>
                        </dl>
                    </div>

                    <div class="rounded-xl border border-amber-200 bg-amber-50 p-5">
                        <h2 class="text-lg font-semibold text-amber-950">Why this exists</h2>
                        <p class="mt-3 text-sm text-amber-900">
                            The demo app doubles as a package-upgrade sandbox, so this screen lets you verify the full checkout state machine without needing to poke a live PSP every time. Mildly less exciting than real money, dramatically better for debugging.
                        </p>
                    </div>
                </div>

                <div class="space-y-4">
                    <div class="rounded-xl border border-slate-200 p-5">
                        <h2 class="text-lg font-semibold text-slate-900">Choose an outcome</h2>
                        <p class="mt-2 text-sm text-slate-600">
                            Each action forwards back through the normal checkout callback routes so orders, failures, and cancellations follow the same backend path as a real gateway redirect.
                        </p>
                    </div>

                    <form method="POST" action="{{ route('demo.payment.process', ['checkoutSession' => $checkoutSession, 'decision' => 'success']) }}">
                        @csrf
                        <button type="submit" class="w-full rounded-xl bg-emerald-600 px-5 py-4 text-left text-white shadow-sm transition hover:bg-emerald-700">
                            <span class="block text-base font-semibold">✅ Mark payment successful</span>
                            <span class="mt-1 block text-sm text-emerald-100">Completes payment verification and continues order creation.</span>
                        </button>
                    </form>

                    <form method="POST" action="{{ route('demo.payment.process', ['checkoutSession' => $checkoutSession, 'decision' => 'failure']) }}">
                        @csrf
                        <button type="submit" class="w-full rounded-xl bg-rose-600 px-5 py-4 text-left text-white shadow-sm transition hover:bg-rose-700">
                            <span class="block text-base font-semibold">❌ Mark payment failed</span>
                            <span class="mt-1 block text-sm text-rose-100">Exercises the checkout failure path and retry UX.</span>
                        </button>
                    </form>

                    <form method="POST" action="{{ route('demo.payment.process', ['checkoutSession' => $checkoutSession, 'decision' => 'cancel']) }}">
                        @csrf
                        <button type="submit" class="w-full rounded-xl bg-slate-700 px-5 py-4 text-left text-white shadow-sm transition hover:bg-slate-800">
                            <span class="block text-base font-semibold">↩️ Cancel payment</span>
                            <span class="mt-1 block text-sm text-slate-200">Exercises the checkout cancel path without charging anything.</span>
                        </button>
                    </form>

                    <a href="{{ route('shop.checkout') }}" class="inline-flex items-center text-sm font-medium text-amber-600 hover:text-amber-700">
                        ← Back to checkout
                    </a>
                </div>
            </div>
        </div>
    </div>
</x-shop-layout>
