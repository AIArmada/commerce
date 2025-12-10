# Database Schema

> **Document:** 05 of 06  
> **Package:** `aiarmada/tax`  
> **Status:** Vision

---

## Tables

### tax_zones

```sql
CREATE TABLE tax_zones (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    code VARCHAR(50) UNIQUE NOT NULL,
    type VARCHAR(20) NOT NULL DEFAULT 'country',
    countries JSON NULL,
    states JSON NULL,
    postal_codes JSON NULL,
    is_default BOOLEAN NOT NULL DEFAULT FALSE,
    priority INT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    
    INDEX idx_tax_zones_type (type),
    INDEX idx_tax_zones_default (is_default),
    INDEX idx_tax_zones_priority (priority)
);
```

### tax_classes

```sql
CREATE TABLE tax_classes (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    code VARCHAR(50) UNIQUE NOT NULL,
    description TEXT NULL,
    is_default BOOLEAN NOT NULL DEFAULT FALSE,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    
    INDEX idx_tax_classes_default (is_default)
);
```

### tax_rates

```sql
CREATE TABLE tax_rates (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    tax_zone_id BIGINT UNSIGNED NOT NULL,
    tax_class_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(100) NOT NULL,
    rate DECIMAL(8,4) NOT NULL DEFAULT 0,
    is_compound BOOLEAN NOT NULL DEFAULT FALSE,
    is_shipping BOOLEAN NOT NULL DEFAULT TRUE,
    priority INT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    
    FOREIGN KEY (tax_zone_id) REFERENCES tax_zones(id) ON DELETE CASCADE,
    FOREIGN KEY (tax_class_id) REFERENCES tax_classes(id) ON DELETE CASCADE,
    UNIQUE KEY uk_tax_rates_unique (tax_zone_id, tax_class_id, priority),
    INDEX idx_tax_rates_zone (tax_zone_id),
    INDEX idx_tax_rates_class (tax_class_id)
);
```

### tax_exemptions

```sql
CREATE TABLE tax_exemptions (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    customer_id BIGINT UNSIGNED NOT NULL,
    tax_zone_id BIGINT UNSIGNED NULL,
    certificate_number VARCHAR(100) NULL,
    certificate_file VARCHAR(255) NULL,
    reason TEXT NULL,
    is_verified BOOLEAN NOT NULL DEFAULT FALSE,
    verified_by BIGINT UNSIGNED NULL,
    verified_at TIMESTAMP NULL,
    starts_at TIMESTAMP NULL,
    expires_at TIMESTAMP NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    
    INDEX idx_tax_exemptions_customer (customer_id),
    INDEX idx_tax_exemptions_zone (tax_zone_id),
    INDEX idx_tax_exemptions_expires (expires_at)
);
```

---

## Seeder Data

### Default Tax Classes
```php
[
    ['code' => 'standard', 'name' => 'Standard Rate', 'is_default' => true],
    ['code' => 'reduced', 'name' => 'Reduced Rate'],
    ['code' => 'zero', 'name' => 'Zero Rate'],
    ['code' => 'exempt', 'name' => 'Tax Exempt'],
]
```

### Malaysia Zone
```php
[
    'name' => 'Malaysia',
    'code' => 'my',
    'type' => 'country',
    'countries' => ['MY'],
    'is_default' => true,
]
```

### Malaysia SST Rate
```php
[
    'zone' => 'my',
    'class' => 'standard',
    'name' => 'SST',
    'rate' => 6.0000,
    'is_shipping' => true,
]
```

---

## Navigation

**Previous:** [04-tax-classes.md](04-tax-classes.md)  
**Next:** [06-implementation-roadmap.md](06-implementation-roadmap.md)
