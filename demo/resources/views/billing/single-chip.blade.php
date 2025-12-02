<!DOCTYPE html>
<html>
<head>
    <title>Single Purchase - {{ $product->name }}</title>
    <script src="https://js.chip.co/v1/"></script>
</head>
<body>
    <h1>Buy {{ $product->name }} for RM{{ number_format($product->price / 100, 2) }}</h1>
    
    <form id="payment-form" action="{{ route('checkout.single.process') }}" method="POST">
        @csrf
        <input type="hidden" name="product_id" value="{{ $product->id }}">
        
        <div>
            <label>Name</label>
            <input type="text" name="name" required>
        </div>
        <div>
            <label>Email</label>
            <input type="email" name="email" required>
        </div>
        
        <div id="chip-element">
            <!-- Chip Elements mounts here -->
        </div>
        <input type="hidden" name="chip_token" id="chip_token">
        
        <button type="submit">Pay Now</button>
    </form>

    <script>
        const chip = new Chip({
            publicKey: '{{ config("chip.public_key") }}',
            environment: '{{ config("chip.environment") }}'
        });

        const elements = chip.elements();
        const card = elements.create('card');
        card.mount('#chip-element');

        document.getElementById('payment-form').addEventListener('submit', async (e) => {
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