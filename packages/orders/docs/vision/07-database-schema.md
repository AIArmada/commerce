# Database Schema

> **Document:** 07 of 08  
> **Package:** `aiarmada/orders`  
> **Status:** Vision

---

## Overview

This document details the database schema for the Orders package.

---

## Tables

### orders

```sql
CREATE TABLE orders (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    order_number VARCHAR(32) UNIQUE NOT NULL,
    customer_id BIGINT UNSIGNED NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'pending_payment',
    currency CHAR(3) NOT NULL DEFAULT 'MYR',
    subtotal BIGINT NOT NULL DEFAULT 0,
    discount_total BIGINT NOT NULL DEFAULT 0,
    shipping_total BIGINT NOT NULL DEFAULT 0,
    tax_total BIGINT NOT NULL DEFAULT 0,
    grand_total BIGINT NOT NULL DEFAULT 0,
    notes TEXT NULL,
    paid_at TIMESTAMP NULL,
    shipped_at TIMESTAMP NULL,
    delivered_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    canceled_at TIMESTAMP NULL,
    cancellation_reason VARCHAR(255) NULL,
    canceled_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    
    INDEX idx_orders_customer (customer_id),
    INDEX idx_orders_status (status),
    INDEX idx_orders_created (created_at),
    INDEX idx_orders_paid (paid_at)
);
```

### order_items

```sql
CREATE TABLE order_items (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    order_id BIGINT UNSIGNED NOT NULL,
    purchasable_type VARCHAR(100) NOT NULL,
    purchasable_id BIGINT UNSIGNED NOT NULL,
    sku VARCHAR(100) NULL,
    name VARCHAR(255) NOT NULL,
    options JSON NULL,
    quantity INT UNSIGNED NOT NULL DEFAULT 1,
    unit_price BIGINT NOT NULL DEFAULT 0,
    unit_discount BIGINT NOT NULL DEFAULT 0,
    unit_tax BIGINT NOT NULL DEFAULT 0,
    line_total BIGINT NOT NULL DEFAULT 0,
    tax_class VARCHAR(50) NULL,
    weight DECIMAL(8,2) NULL DEFAULT 0,
    is_digital BOOLEAN NOT NULL DEFAULT FALSE,
    fulfilled_quantity INT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    INDEX idx_order_items_order (order_id),
    INDEX idx_order_items_purchasable (purchasable_type, purchasable_id)
);
```

### order_addresses

```sql
CREATE TABLE order_addresses (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    order_id BIGINT UNSIGNED NOT NULL,
    type VARCHAR(20) NOT NULL,  -- billing, shipping
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NULL,
    company VARCHAR(150) NULL,
    address_line_1 VARCHAR(255) NOT NULL,
    address_line_2 VARCHAR(255) NULL,
    city VARCHAR(100) NOT NULL,
    state VARCHAR(100) NULL,
    postal_code VARCHAR(20) NULL,
    country_code CHAR(2) NOT NULL,
    phone VARCHAR(50) NULL,
    email VARCHAR(255) NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    INDEX idx_order_addresses_order (order_id)
);
```

### order_payments

```sql
CREATE TABLE order_payments (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    order_id BIGINT UNSIGNED NOT NULL,
    gateway VARCHAR(50) NOT NULL,
    transaction_id VARCHAR(255) NULL,
    amount BIGINT NOT NULL,
    currency CHAR(3) NOT NULL DEFAULT 'MYR',
    status VARCHAR(30) NOT NULL DEFAULT 'pending',
    payment_method VARCHAR(50) NULL,
    card_last_four CHAR(4) NULL,
    card_brand VARCHAR(30) NULL,
    metadata JSON NULL,
    paid_at TIMESTAMP NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    INDEX idx_order_payments_order (order_id),
    INDEX idx_order_payments_transaction (transaction_id)
);
```

### order_refunds

```sql
CREATE TABLE order_refunds (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    order_id BIGINT UNSIGNED NOT NULL,
    payment_id BIGINT UNSIGNED NULL,
    amount BIGINT NOT NULL,
    reason VARCHAR(255) NULL,
    notes TEXT NULL,
    gateway_refund_id VARCHAR(255) NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'pending',
    refunded_by BIGINT UNSIGNED NULL,
    refunded_at TIMESTAMP NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (payment_id) REFERENCES order_payments(id) ON DELETE SET NULL,
    INDEX idx_order_refunds_order (order_id)
);
```

### order_notes

```sql
CREATE TABLE order_notes (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    order_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NULL,
    content TEXT NOT NULL,
    is_customer_visible BOOLEAN NOT NULL DEFAULT FALSE,
    type VARCHAR(20) NOT NULL DEFAULT 'internal',
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    INDEX idx_order_notes_order (order_id)
);
```

### order_history

```sql
CREATE TABLE order_history (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    order_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NULL,
    event VARCHAR(50) NOT NULL,
    description VARCHAR(255) NULL,
    old_values JSON NULL,
    new_values JSON NULL,
    metadata JSON NULL,
    created_at TIMESTAMP NULL,
    
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    INDEX idx_order_history_order (order_id),
    INDEX idx_order_history_event (event)
);
```

### order_sequences

```sql
CREATE TABLE order_sequences (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    month CHAR(6) NOT NULL,  -- YYYYMM
    sequence INT UNSIGNED NOT NULL DEFAULT 0,
    
    UNIQUE KEY uk_order_sequences_month (month)
);
```

---

## Indexes for Performance

```sql
-- For dashboard queries
CREATE INDEX idx_orders_daily_stats ON orders (created_at, status, grand_total);

-- For customer order history
CREATE INDEX idx_orders_customer_history ON orders (customer_id, created_at DESC);

-- For fulfillment queue
CREATE INDEX idx_orders_fulfillment ON orders (status, created_at) 
    WHERE status IN ('processing', 'shipped');
```

---

## Money Handling

All monetary values are stored as integers in the smallest currency unit (cents/sen):

| Column | Type | Example |
|--------|------|---------|
| `subtotal` | BIGINT | 10000 = RM 100.00 |
| `grand_total` | BIGINT | 10600 = RM 106.00 |
| `unit_price` | BIGINT | 2500 = RM 25.00 |

---

## Navigation

**Previous:** [06-integration.md](06-integration.md)  
**Next:** [08-implementation-roadmap.md](08-implementation-roadmap.md)
