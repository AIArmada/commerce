<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Commerce Demo</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
        }
        .container {
            text-align: center;
            max-width: 800px;
            padding: 2rem;
        }
        .logo {
            font-size: 4rem;
            margin-bottom: 1rem;
        }
        h1 {
            font-size: 3rem;
            margin-bottom: 0.5rem;
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .subtitle {
            font-size: 1.25rem;
            color: #94a3b8;
            margin-bottom: 2rem;
        }
        .features {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin: 3rem 0;
        }
        .feature {
            background: rgba(255,255,255,0.05);
            padding: 1.5rem;
            border-radius: 1rem;
            border: 1px solid rgba(255,255,255,0.1);
        }
        .feature-icon {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }
        .feature h3 {
            font-size: 1rem;
            margin-bottom: 0.25rem;
        }
        .feature p {
            font-size: 0.875rem;
            color: #94a3b8;
        }
        .cta {
            margin-top: 2rem;
        }
        .btn {
            display: inline-block;
            padding: 1rem 2rem;
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            color: #1a1a2e;
            text-decoration: none;
            border-radius: 0.5rem;
            font-weight: 600;
            font-size: 1rem;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(245, 158, 11, 0.3);
        }
        .packages {
            margin-top: 3rem;
            padding-top: 2rem;
            border-top: 1px solid rgba(255,255,255,0.1);
        }
        .packages h2 {
            font-size: 1.5rem;
            margin-bottom: 1rem;
            color: #f59e0b;
        }
        .package-list {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            justify-content: center;
        }
        .package-tag {
            background: rgba(245, 158, 11, 0.1);
            color: #f59e0b;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.875rem;
            border: 1px solid rgba(245, 158, 11, 0.3);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">🛒</div>
        <h1>Commerce Demo</h1>
        <p class="subtitle">A fully functional demo showcasing all AIArmada Commerce packages</p>

        <div class="features">
            <div class="feature">
                <div class="feature-icon">🛍️</div>
                <h3>Shopping Cart</h3>
                <p>Full-featured cart with conditions</p>
            </div>
            <div class="feature">
                <div class="feature-icon">🎟️</div>
                <h3>Vouchers</h3>
                <p>Discount codes & promotions</p>
            </div>
            <div class="feature">
                <div class="feature-icon">📦</div>
                <h3>Inventory Management</h3>
                <p>Multi-location inventory tracking & alerts</p>
            </div>
            <div class="feature">
                <div class="feature-icon">🤝</div>
                <h3>Affiliates</h3>
                <p>Partner program & tracking</p>
            </div>
            <div class="feature">
                <div class="feature-icon">💳</div>
                <h3>Payments</h3>
                <p>Chip payment integration</p>
            </div>
            <div class="feature">
                <div class="feature-icon">🚚</div>
                <h3>Shipping</h3>
                <p>J&T Express integration</p>
            </div>
        </div>

        <div class="cta">
            <a href="/admin" class="btn">Open Admin Panel →</a>
        </div>

        <div class="packages">
            <h2>Included Packages</h2>
            <div class="package-list">
                <span class="package-tag">cart</span>
                <span class="package-tag">cashier</span>
                <span class="package-tag">vouchers</span>
                <span class="package-tag">inventory</span>
                <span class="package-tag">affiliates</span>
                <span class="package-tag">chip</span>
                <span class="package-tag">jnt</span>
                <span class="package-tag">filament-cart</span>
                <span class="package-tag">filament-vouchers</span>
                <span class="package-tag">filament-inventory</span>
                <span class="package-tag">filament-affiliates</span>
                <span class="package-tag">filament-chip</span>
                <span class="package-tag">filament-jnt</span>
            </div>
        </div>
    </div>
</body>
</html>
