# Production Deployment Guide

Comprehensive guide for deploying AIArmada Commerce packages to production.

## Pre-Deployment Checklist

### 1. Environment Configuration

#### Required Environment Variables

```env
# Application
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com

# Database
DB_CONNECTION=pgsql
DB_HOST=your-db-host
DB_DATABASE=your-database
DB_USERNAME=your-username
DB_PASSWORD=your-secure-password

# Cart
CART_STORAGE_DRIVER=database
CART_DEFAULT_CURRENCY=MYR

# CHIP Payment Gateway
CHIP_COLLECT_API_KEY=your-production-api-key
CHIP_COLLECT_BRAND_ID=your-brand-id
CHIP_COLLECT_ENVIRONMENT=production
CHIP_SEND_API_KEY=your-send-api-key
CHIP_SEND_API_SECRET=your-send-secret
CHIP_SEND_ENVIRONMENT=production
CHIP_WEBHOOKS_PUBLIC_KEY=your-public-key

# J&T Express
JNT_API_KEY=your-production-api-key
JNT_API_SECRET=your-production-secret
JNT_ENVIRONMENT=production

# Cache & Queue
CACHE_DRIVER=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis

# Redis
REDIS_HOST=your-redis-host
REDIS_PASSWORD=your-redis-password
REDIS_PORT=6379
```

### 2. Security Hardening

#### SSL/TLS Configuration

```nginx
# Nginx configuration
server {
    listen 443 ssl http2;
    server_name your-domain.com;
    
    ssl_certificate /path/to/certificate.crt;
    ssl_certificate_key /path/to/private.key;
    
    # Strong SSL configuration
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;
    ssl_prefer_server_ciphers on;
    
    # HSTS
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
    
    location / {
        proxy_pass http://127.0.0.1:8000;
        proxy_set_header Host $host;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
```

#### Webhook Security

```php
// Verify all webhook signatures
// Automatically handled by package, but verify config:

// config/chip.php
'webhooks' => [
    'verify_signature' => true, // MUST be true in production
    'public_key' => env('CHIP_WEBHOOKS_PUBLIC_KEY'),
],
```

### 3. Database Optimization

#### PostgreSQL Configuration

```sql
-- Recommended indexes
CREATE INDEX idx_carts_user_id ON carts(user_id);
CREATE INDEX idx_carts_identifier ON carts(identifier);
CREATE INDEX idx_cart_items_cart_id ON cart_items(cart_id);
CREATE INDEX idx_chip_purchases_reference ON chip_purchases(reference);
CREATE INDEX idx_chip_purchases_status ON chip_purchases(status);
CREATE INDEX idx_vouchers_code ON vouchers(code);
CREATE INDEX idx_voucher_redemptions_voucher_id ON voucher_redemptions(voucher_id);
```

#### Database Connection Pooling

```env
# Use PgBouncer for PostgreSQL
DB_HOST=pgbouncer-host
DB_PORT=6432
```

### 4. Cache Configuration

#### Redis Setup

```php
// config/database.php
'redis' => [
    'client' => 'phpredis',
    'options' => [
        'cluster' => env('REDIS_CLUSTER', 'redis'),
        'prefix' => env('REDIS_PREFIX', 'commerce_'),
    ],
    'default' => [
        'url' => env('REDIS_URL'),
        'host' => env('REDIS_HOST', '127.0.0.1'),
        'password' => env('REDIS_PASSWORD'),
        'port' => env('REDIS_PORT', 6379),
        'database' => 0,
        'read_timeout' => 60,
        'retry_interval' => 100,
    ],
],
```

#### Cache Warming

```bash
# Pre-cache common data
php artisan cache:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### 5. Queue Configuration

#### Supervisor Configuration

```ini
; /etc/supervisor/conf.d/laravel-worker.conf
[program:laravel-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=4
redirect_stderr=true
stdout_logfile=/var/www/storage/logs/worker.log
stopwaitsecs=3600
```

```bash
# Start supervisor
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start laravel-worker:*
```

## Deployment Process

### 1. Code Deployment

```bash
# On production server
cd /var/www/your-app

# Pull latest code
git pull origin main

# Install dependencies (no dev packages)
composer install --optimize-autoloader --no-dev

# Install frontend assets
npm ci --production
npm run build
```

### 2. Database Migrations

```bash
# Backup database first
php artisan db:backup

# Run migrations
php artisan migrate --force

# Seed production data if needed
php artisan db:seed --class=ProductionSeeder
```

### 3. Cache & Optimization

```bash
# Clear all caches
php artisan optimize:clear

# Cache configurations
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Cache events
php artisan event:cache
```

### 4. File Permissions

```bash
# Set correct ownership
sudo chown -R www-data:www-data /var/www/your-app

# Set directory permissions
sudo find /var/www/your-app -type d -exec chmod 755 {} \;

# Set file permissions
sudo find /var/www/your-app -type f -exec chmod 644 {} \;

# Storage and cache writable
sudo chmod -R 775 storage bootstrap/cache
```

### 5. Health Checks

```bash
# Verify application health
curl https://your-domain.com/health

# Check queue workers
php artisan queue:monitor

# Check scheduled tasks
php artisan schedule:list
```

## Monitoring

### 1. Application Monitoring

#### Laravel Telescope

```bash
# Install Telescope (development only)
composer require laravel/telescope --dev

# Publish config
php artisan telescope:install

# Run migrations
php artisan migrate
```

⚠️ **Important**: Restrict Telescope access in production:

```php
// app/Providers/TelescopeServiceProvider.php
protected function gate(): void
{
    Gate::define('viewTelescope', function ($user) {
        return in_array($user->email, [
            'admin@your-domain.com',
        ]);
    });
}
```

#### Laravel Horizon (Queue Dashboard)

```bash
# Install Horizon
composer require laravel/horizon

# Publish config
php artisan horizon:install

# Start Horizon
php artisan horizon
```

### 2. Log Monitoring

```bash
# Watch real-time logs
tail -f storage/logs/laravel.log

# Monitor error logs
grep "ERROR" storage/logs/laravel.log

# Monitor CHIP webhooks
grep "CHIP Webhook" storage/logs/laravel.log
```

### 3. Performance Monitoring

#### New Relic Configuration

```env
# .env
NEW_RELIC_ENABLED=true
NEW_RELIC_APP_NAME="Your App Name"
NEW_RELIC_LICENSE_KEY=your-license-key
```

#### Datadog Configuration

```bash
# Install Datadog PHP tracer
composer require datadog/dd-trace

# Configure
export DD_SERVICE="your-app"
export DD_ENV="production"
export DD_VERSION="0.1.0"
```

### 4. Uptime Monitoring

Set up external monitoring:
- **Pingdom**: Monitor uptime
- **UptimeRobot**: Free uptime checks
- **StatusCake**: API endpoint monitoring

Monitor these endpoints:
- `https://your-domain.com/health`
- `https://your-domain.com/webhooks/chip/{id}` (verify accessibility)

## Backup Strategy

### 1. Database Backups

```bash
# Daily automated backup
0 2 * * * cd /var/www/your-app && php artisan db:backup

# Weekly full backup
0 3 * * 0 pg_dump your_database > /backups/weekly_$(date +\%Y\%m\%d).sql
```

### 2. File Backups

```bash
# Backup user uploads
0 4 * * * rsync -avz /var/www/storage/app/public /backups/uploads/

# Backup entire application
0 5 * * * tar -czf /backups/app_$(date +\%Y\%m\%d).tar.gz /var/www/your-app
```

### 3. Backup Retention

- **Daily backups**: Keep 7 days
- **Weekly backups**: Keep 4 weeks
- **Monthly backups**: Keep 12 months

## Scaling Considerations

### Horizontal Scaling

#### Load Balancer Configuration

```nginx
# Nginx load balancer
upstream app_servers {
    least_conn;
    server app1.local:8000 weight=10;
    server app2.local:8000 weight=10;
    server app3.local:8000 weight=5;
}

server {
    listen 443 ssl http2;
    
    location / {
        proxy_pass http://app_servers;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    }
}
```

#### Session Storage

```env
# Use Redis for shared sessions
SESSION_DRIVER=redis
SESSION_CONNECTION=default
```

### Database Scaling

#### Read Replicas

```php
// config/database.php
'pgsql' => [
    'read' => [
        'host' => [
            'replica1.local',
            'replica2.local',
        ],
    ],
    'write' => [
        'host' => ['master.local'],
    ],
    // ... other config
],
```

#### Database Sharding (Advanced)

For high-traffic applications, consider:
- Shard by user ID
- Separate databases for cart vs. payment data
- Use read replicas for reporting

### Cache Scaling

```env
# Redis Cluster
REDIS_CLUSTER=redis
REDIS_CLUSTER_NODES=node1:6379,node2:6379,node3:6379
```

## Troubleshooting Production Issues

### Common Issues

#### Issue: "Page Expired" (419) after deployment

**Cause**: Session driver changed or cache cleared

**Solution**:
```bash
php artisan config:cache
php artisan session:table
php artisan migrate
```

#### Issue: Webhook signature verification fails

**Cause**: Public key mismatch

**Solution**:
```bash
# Verify public key in .env matches CHIP dashboard
php artisan config:cache

# Test webhook locally
curl -X POST https://your-domain.com/webhooks/chip/{id} \
  -H "Content-Type: application/json" \
  -d '{"event":"test"}'
```

#### Issue: Cart data lost after deployment

**Cause**: Storage driver change

**Solution**:
```php
// Migrate cart data
Cart::store(auth()->id()); // Store before deployment
Cart::restore(auth()->id()); // Restore after deployment
```

#### Issue: High database load

**Solution**:
```bash
# Enable query caching
php artisan config:set database.connections.pgsql.options.PDO::ATTR_PERSISTENT true

# Add indexes
php artisan db:optimize-indexes
```

### Performance Debugging

```bash
# Enable query logging temporarily
php artisan debugbar:enable

# Profile slow endpoints
php artisan telescope:watch

# Check queue status
php artisan queue:failed
php artisan queue:retry all
```

## Security Checklist

- [ ] SSL/TLS certificates valid
- [ ] HTTPS enforced
- [ ] Webhook signature verification enabled
- [ ] API keys in environment variables (not code)
- [ ] Database credentials secured
- [ ] File permissions correct (644 files, 755 dirs)
- [ ] Debug mode disabled (`APP_DEBUG=false`)
- [ ] Production error pages configured
- [ ] CORS headers properly configured
- [ ] Rate limiting enabled on API routes
- [ ] Filament admin panel protected
- [ ] Regular security updates applied

## Post-Deployment

### 1. Smoke Testing

```bash
# Test cart operations
curl https://your-domain.com/api/cart

# Test payment flow
# (Manual test through UI)

# Test voucher redemption
curl -X POST https://your-domain.com/api/vouchers/redeem \
  -H "Content-Type: application/json" \
  -d '{"code":"TEST10"}'
```

### 2. Monitor First 24 Hours

- Check error logs every hour
- Monitor payment success rate
- Watch for webhook failures
- Monitor queue worker status
- Check application performance

### 3. Rollback Plan

Have rollback plan ready:

```bash
# Quick rollback script
#!/bin/bash
cd /var/www/your-app
git checkout previous-tag
composer install --optimize-autoloader --no-dev
php artisan migrate:rollback
php artisan config:cache
php artisan queue:restart
sudo supervisorctl restart laravel-worker:*
```

## Next Steps

- **[Upgrade Guide](05-upgrade-guide.md)**: Plan future upgrades
- **[Support Utilities](04-support-utilities.md)**: Understand shared tools
- **[Package Reference](03-packages/)**: Package-specific production tips
