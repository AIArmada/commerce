<!DOCTYPE html>
<html>
<head>
    <title>Subscribe to {{ $plan }} Plan</title>
    <script src="https://js.stripe.com/v3/"></script>
</head>
<body>
    <h1>Subscribe with Stripe</h1>
    
    <form id="stripe-form">
        <div id="card-element"></div>
        <div id="card-errors" role="alert"></div>
        <input type="hidden" name="plan" value="{{ $plan }}">
        <button id="submit">Subscribe</button>
    </form>

    <script>
        const stripe = Stripe('{{ config("services.stripe.key") }}');
        const elements = stripe.elements();
        const cardElement = elements.create('card');
        cardElement.mount('#card-element');

        const form = document.getElementById('stripe-form');
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const { error, setupIntent } = await stripe.confirmSetupIntent(
                '{{ $intent->client_secret }}'
            );
            if (error) {
                document.getElementById('card-errors').textContent = error.message;
            } else {
                // Submit to server
                fetch('{{ route("subscribe.stripe.process") }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                    body: JSON.stringify({ plan: '{{ $plan }}', stripe_token: setupIntent.payment_method })
                }).then(r => r.json()).then(data => location.href = data.redirect);
            }
        });
    </script>
</body>
</html>