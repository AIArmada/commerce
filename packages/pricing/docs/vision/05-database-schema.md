# Database Schema

> **Document:** 05 of 06  
> **Package:** `aiarmada/pricing`  
> **Status:** Vision

---

## Tables

### price_lists

```sql
CREATE TABLE price_lists (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    code VARCHAR(50) UNIQUE NOT NULL,
    description TEXT NULL,
    currency CHAR(3) NOT NULL DEFAULT 'MYR',
    priority INT UNSIGNED NOT NULL DEFAULT 0,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    starts_at TIMESTAMP NULL,
    ends_at TIMESTAMP NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    
    INDEX idx_price_lists_active (is_active),
    INDEX idx_price_lists_priority (priority)
);
```

### prices

```sql
CREATE TABLE prices (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    price_list_id BIGINT UNSIGNED NOT NULL,
    priceable_type VARCHAR(100) NOT NULL,
    priceable_id BIGINT UNSIGNED NOT NULL,
    price BIGINT NOT NULL,
    compare_at_price BIGINT NULL,
    min_quantity INT UNSIGNED NOT NULL DEFAULT 1,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    
    FOREIGN KEY (price_list_id) REFERENCES price_lists(id) ON DELETE CASCADE,
    INDEX idx_prices_priceable (priceable_type, priceable_id),
    INDEX idx_prices_list (price_list_id),
    UNIQUE KEY uk_prices_unique (price_list_id, priceable_type, priceable_id, min_quantity)
);
```

### price_list_segment

```sql
CREATE TABLE price_list_segment (
    price_list_id BIGINT UNSIGNED NOT NULL,
    segment_id BIGINT UNSIGNED NOT NULL,
    
    PRIMARY KEY (price_list_id, segment_id),
    FOREIGN KEY (price_list_id) REFERENCES price_lists(id) ON DELETE CASCADE
);
```

### price_list_customer_group

```sql
CREATE TABLE price_list_customer_group (
    price_list_id BIGINT UNSIGNED NOT NULL,
    customer_group_id BIGINT UNSIGNED NOT NULL,
    
    PRIMARY KEY (price_list_id, customer_group_id),
    FOREIGN KEY (price_list_id) REFERENCES price_lists(id) ON DELETE CASCADE
);
```

### price_tiers

```sql
CREATE TABLE price_tiers (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    priceable_type VARCHAR(100) NOT NULL,
    priceable_id BIGINT UNSIGNED NOT NULL,
    price_list_id BIGINT UNSIGNED NULL,
    min_quantity INT UNSIGNED NOT NULL,
    max_quantity INT UNSIGNED NULL,
    price BIGINT NULL,
    discount_type VARCHAR(20) NULL,
    discount_value DECIMAL(10,2) NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    
    FOREIGN KEY (price_list_id) REFERENCES price_lists(id) ON DELETE CASCADE,
    INDEX idx_price_tiers_priceable (priceable_type, priceable_id),
    INDEX idx_price_tiers_quantity (min_quantity, max_quantity)
);
```

### price_rules

```sql
CREATE TABLE price_rules (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    description TEXT NULL,
    priority INT UNSIGNED NOT NULL DEFAULT 0,
    conditions JSON NOT NULL,
    actions JSON NOT NULL,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    is_stackable BOOLEAN NOT NULL DEFAULT FALSE,
    usage_limit INT UNSIGNED NULL,
    usage_count INT UNSIGNED NOT NULL DEFAULT 0,
    starts_at TIMESTAMP NULL,
    ends_at TIMESTAMP NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    
    INDEX idx_price_rules_active (is_active),
    INDEX idx_price_rules_priority (priority),
    INDEX idx_price_rules_dates (starts_at, ends_at)
);
```

---

## Navigation

**Previous:** [04-price-rules.md](04-price-rules.md)  
**Next:** [06-implementation-roadmap.md](06-implementation-roadmap.md)
