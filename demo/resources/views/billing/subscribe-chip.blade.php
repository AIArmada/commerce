<!DOCTYPE html>
<html>
<head>
    <title>Subscribe to {{ $planData['name'] }}</title>
</head>
<body>
    <h1>Subscribe to {{ $planData['name'] }} - RM{{ number_format($planData['amount'] / 10000, 2) }} / {{ strtoupper($planData['billing_interval'] ?? 'month') }}</h1>

    <form id="sub-form" action="{{ route('subscribe.chip.process') }}" method="POST">
        @csrf
        <input type="hidden" name="plan" value="{{ request()->route('plan') }}">

        <p>You will be redirected to CHIP's hosted checkout to complete the subscription and save a recurring payment method.</p>

        <button type="submit">Continue to CHIP Checkout</button>
    </form>
</body>
</html>