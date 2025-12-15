# Database Schema

> **Document:** 05 of 06  
> **Package:** `aiarmada/customers`  
> **Status:** Vision

---

## Tables

### customers

```sql
CREATE TABLE customers (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NULL,
    email VARCHAR(255) NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NULL,
    phone VARCHAR(50) NULL,
    company VARCHAR(150) NULL,
    tax_number VARCHAR(50) NULL,
    accepts_marketing BOOLEAN NOT NULL DEFAULT FALSE,
    locale VARCHAR(10) NULL DEFAULT 'en',
    currency CHAR(3) NOT NULL DEFAULT 'MYR',
    notes TEXT NULL,
    metadata JSON NULL,
    last_order_at TIMESTAMP NULL,
    orders_count INT UNSIGNED NOT NULL DEFAULT 0,
    total_spent BIGINT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_customers_user (user_id),
    INDEX idx_customers_email (email),
    INDEX idx_customers_marketing (accepts_marketing),
    INDEX idx_customers_last_order (last_order_at)
);
```

### addresses

```sql
CREATE TABLE addresses (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    customer_id BIGINT UNSIGNED NOT NULL,
    label VARCHAR(50) NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NULL,
    company VARCHAR(150) NULL,
    address_line_1 VARCHAR(255) NOT NULL,
    address_line_2 VARCHAR(255) NULL,
    city VARCHAR(100) NOT NULL,
    state VARCHAR(100) NULL,
    postal_code VARCHAR(20) NOT NULL,
    country_code CHAR(2) NOT NULL,
    phone VARCHAR(50) NULL,
    is_default_billing BOOLEAN NOT NULL DEFAULT FALSE,
    is_default_shipping BOOLEAN NOT NULL DEFAULT FALSE,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    INDEX idx_addresses_customer (customer_id),
    INDEX idx_addresses_default_billing (customer_id, is_default_billing),
    INDEX idx_addresses_default_shipping (customer_id, is_default_shipping)
);
```

### segments

```sql
CREATE TABLE segments (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) UNIQUE NOT NULL,
    description TEXT NULL,
    type VARCHAR(20) NOT NULL DEFAULT 'manual',
    conditions JSON NULL,
    match_type VARCHAR(10) NOT NULL DEFAULT 'all',
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    priority INT UNSIGNED NOT NULL DEFAULT 0,
    cached_count INT UNSIGNED NOT NULL DEFAULT 0,
    last_synced_at TIMESTAMP NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    
    INDEX idx_segments_type (type),
    INDEX idx_segments_active (is_active)
);
```

### customer_segment

```sql
CREATE TABLE customer_segment (
    customer_id BIGINT UNSIGNED NOT NULL,
    segment_id BIGINT UNSIGNED NOT NULL,
    
    PRIMARY KEY (customer_id, segment_id),
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    FOREIGN KEY (segment_id) REFERENCES segments(id) ON DELETE CASCADE
);
```

### customer_groups

```sql
CREATE TABLE customer_groups (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    code VARCHAR(50) UNIQUE NOT NULL,
    is_default BOOLEAN NOT NULL DEFAULT FALSE,
    discount_percent DECIMAL(5,2) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
);
```

### customer_customer_group

```sql
CREATE TABLE customer_customer_group (
    customer_id BIGINT UNSIGNED NOT NULL,
    customer_group_id BIGINT UNSIGNED NOT NULL,
    
    PRIMARY KEY (customer_id, customer_group_id),
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    FOREIGN KEY (customer_group_id) REFERENCES customer_groups(id) ON DELETE CASCADE
);
```

### wishlists

```sql
CREATE TABLE wishlists (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    customer_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(100) NOT NULL DEFAULT 'Default',
    is_default BOOLEAN NOT NULL DEFAULT FALSE,
    is_public BOOLEAN NOT NULL DEFAULT FALSE,
    share_token VARCHAR(32) NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    INDEX idx_wishlists_customer (customer_id),
    INDEX idx_wishlists_share (share_token)
);
```

### wishlist_items

```sql
CREATE TABLE wishlist_items (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    wishlist_id BIGINT UNSIGNED NOT NULL,
    product_type VARCHAR(100) NOT NULL,
    product_id BIGINT UNSIGNED NOT NULL,
    added_at TIMESTAMP NOT NULL,
    
    FOREIGN KEY (wishlist_id) REFERENCES wishlists(id) ON DELETE CASCADE,
    INDEX idx_wishlist_items_wishlist (wishlist_id),
    INDEX idx_wishlist_items_product (product_type, product_id)
);
```

### customer_notes

```sql
CREATE TABLE customer_notes (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    customer_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NULL,
    content TEXT NOT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    INDEX idx_customer_notes_customer (customer_id)
);
```

---

## Navigation

**Previous:** [04-segments-groups.md](04-segments-groups.md)  
**Next:** [06-implementation-roadmap.md](06-implementation-roadmap.md)
