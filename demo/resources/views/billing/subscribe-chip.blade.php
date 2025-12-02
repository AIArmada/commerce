<!DOCTYPE html>
<html>
<head>
    <title>Subscribe to {{ $planData['name'] }}</title>
    <script src="https://js.chip.co/v1/"></script>
</head>
<body>
    <h1>Subscribe to {{ $planData['name'] }} - RM{{ number_format($planData['amount'] / 10000, 2) }} / {{ strtoupper($planData['billing_interval'] ?? 'month') }}</h1>
    
    <form id="sub-form" action="{{ route('subscribe.chip.process') }}" method="POST">
        @csrf
        <input type="hidden" name="plan" value="{{ request()->route('plan') }}">
        
        <div id="chip-element">
            <!-- Chip Elements -->
        </div>
        <input type="hidden" name="chip_token" id="chip_token">
        
        <button type="submit">Subscribe Now</button>
    </form>

    <script>
        const chip = new Chip({
            publicKey: '{{ config("chip.public_key") }}',
            environment: '{{ config("chip.environment") }}'
        });

        const elements = chip.elements();
        const card = elements.create('card');
        card.mount('#chip-element');

        document.getElementById('sub-form').addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const { token, error } = await chip.createToken(card);
            if (error) {
                alert(error.message);
            } else {
                document.getElementById('chip_token').value = token.id;
                e.target.submit();
            }
        });
    </script>
</body>
</html>