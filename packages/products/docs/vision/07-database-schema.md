# Database Schema

> **Document:** 07 of 08  
> **Package:** `aiarmada/products`  
> **Status:** Vision

---

## Overview

This document details the database schema for the Products package.

---

## Core Tables

### products

```sql
CREATE TABLE products (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    type VARCHAR(30) NOT NULL DEFAULT 'simple',
    status VARCHAR(30) NOT NULL DEFAULT 'draft',
    visibility JSON NULL,
    sku VARCHAR(100) NULL,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) UNIQUE NOT NULL,
    description TEXT NULL,
    short_description TEXT NULL,
    meta_title VARCHAR(255) NULL,
    meta_description TEXT NULL,
    price BIGINT NOT NULL DEFAULT 0,
    compare_at_price BIGINT NULL,
    cost_price BIGINT NULL,
    tax_class VARCHAR(50) NULL,
    weight DECIMAL(8,2) NULL,
    is_featured BOOLEAN NOT NULL DEFAULT FALSE,
    published_at TIMESTAMP NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    
    INDEX idx_products_type (type),
    INDEX idx_products_status (status),
    INDEX idx_products_sku (sku),
    INDEX idx_products_featured (is_featured),
    INDEX idx_products_published (published_at)
);
```

### product_variants

```sql
CREATE TABLE product_variants (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    product_id BIGINT UNSIGNED NOT NULL,
    sku VARCHAR(100) UNIQUE NOT NULL,
    name VARCHAR(255) NULL,
    price BIGINT NULL,
    compare_at_price BIGINT NULL,
    cost_price BIGINT NULL,
    weight DECIMAL(8,2) NULL,
    position INT UNSIGNED NOT NULL DEFAULT 0,
    is_default BOOLEAN NOT NULL DEFAULT FALSE,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    INDEX idx_variants_product (product_id),
    INDEX idx_variants_sku (sku)
);
```

### product_options

```sql
CREATE TABLE product_options (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    product_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(100) NOT NULL,
    display_name VARCHAR(100) NULL,
    position INT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    INDEX idx_options_product (product_id)
);
```

### product_option_values

```sql
CREATE TABLE product_option_values (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    option_id BIGINT UNSIGNED NOT NULL,
    value VARCHAR(100) NOT NULL,
    label VARCHAR(100) NULL,
    swatch_type VARCHAR(20) NULL,
    swatch_value VARCHAR(100) NULL,
    position INT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    
    FOREIGN KEY (option_id) REFERENCES product_options(id) ON DELETE CASCADE,
    INDEX idx_option_values_option (option_id)
);
```

### variant_option_values

Pivot table linking variants to their option values.

```sql
CREATE TABLE variant_option_values (
    variant_id BIGINT UNSIGNED NOT NULL,
    option_value_id BIGINT UNSIGNED NOT NULL,
    
    PRIMARY KEY (variant_id, option_value_id),
    FOREIGN KEY (variant_id) REFERENCES product_variants(id) ON DELETE CASCADE,
    FOREIGN KEY (option_value_id) REFERENCES product_option_values(id) ON DELETE CASCADE
);
```

---

## Category Tables

### categories

```sql
CREATE TABLE categories (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    parent_id BIGINT UNSIGNED NULL,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) UNIQUE NOT NULL,
    description TEXT NULL,
    meta_title VARCHAR(255) NULL,
    meta_description TEXT NULL,
    position INT UNSIGNED NOT NULL DEFAULT 0,
    is_visible BOOLEAN NOT NULL DEFAULT TRUE,
    _lft INT UNSIGNED NOT NULL DEFAULT 0,
    _rgt INT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    
    FOREIGN KEY (parent_id) REFERENCES categories(id) ON DELETE SET NULL,
    INDEX idx_categories_parent (parent_id),
    INDEX idx_categories_nested (_lft, _rgt)
);
```

### category_product

```sql
CREATE TABLE category_product (
    category_id BIGINT UNSIGNED NOT NULL,
    product_id BIGINT UNSIGNED NOT NULL,
    position INT UNSIGNED NOT NULL DEFAULT 0,
    
    PRIMARY KEY (category_id, product_id),
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);
```

---

## Collection Tables

### collections

```sql
CREATE TABLE collections (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    type VARCHAR(20) NOT NULL DEFAULT 'manual',
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) UNIQUE NOT NULL,
    description TEXT NULL,
    conditions JSON NULL,
    match_type VARCHAR(10) NOT NULL DEFAULT 'all',
    is_featured BOOLEAN NOT NULL DEFAULT FALSE,
    published_at TIMESTAMP NULL,
    unpublished_at TIMESTAMP NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    
    INDEX idx_collections_type (type),
    INDEX idx_collections_featured (is_featured),
    INDEX idx_collections_published (published_at)
);
```

### collection_product

```sql
CREATE TABLE collection_product (
    collection_id BIGINT UNSIGNED NOT NULL,
    product_id BIGINT UNSIGNED NOT NULL,
    position INT UNSIGNED NOT NULL DEFAULT 0,
    
    PRIMARY KEY (collection_id, product_id),
    FOREIGN KEY (collection_id) REFERENCES collections(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);
```

---

## Attribute Tables

### attributes

```sql
CREATE TABLE attributes (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    code VARCHAR(100) UNIQUE NOT NULL,
    name VARCHAR(255) NOT NULL,
    type VARCHAR(30) NOT NULL DEFAULT 'text',
    validation JSON NULL,
    options JSON NULL,
    is_required BOOLEAN NOT NULL DEFAULT FALSE,
    is_filterable BOOLEAN NOT NULL DEFAULT FALSE,
    is_searchable BOOLEAN NOT NULL DEFAULT FALSE,
    is_comparable BOOLEAN NOT NULL DEFAULT FALSE,
    is_visible_on_front BOOLEAN NOT NULL DEFAULT TRUE,
    position INT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    
    INDEX idx_attributes_code (code),
    INDEX idx_attributes_filterable (is_filterable),
    INDEX idx_attributes_searchable (is_searchable)
);
```

### attribute_groups

```sql
CREATE TABLE attribute_groups (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    code VARCHAR(50) UNIQUE NOT NULL,
    position INT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
);
```

### attribute_group_attribute

```sql
CREATE TABLE attribute_group_attribute (
    attribute_group_id BIGINT UNSIGNED NOT NULL,
    attribute_id BIGINT UNSIGNED NOT NULL,
    position INT UNSIGNED NOT NULL DEFAULT 0,
    
    PRIMARY KEY (attribute_group_id, attribute_id),
    FOREIGN KEY (attribute_group_id) REFERENCES attribute_groups(id) ON DELETE CASCADE,
    FOREIGN KEY (attribute_id) REFERENCES attributes(id) ON DELETE CASCADE
);
```

### attribute_values

```sql
CREATE TABLE attribute_values (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    attribute_id BIGINT UNSIGNED NOT NULL,
    attributable_type VARCHAR(100) NOT NULL,
    attributable_id BIGINT UNSIGNED NOT NULL,
    value TEXT NULL,
    locale VARCHAR(10) NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    
    FOREIGN KEY (attribute_id) REFERENCES attributes(id) ON DELETE CASCADE,
    INDEX idx_attribute_values_attributable (attributable_type, attributable_id),
    INDEX idx_attribute_values_attribute (attribute_id)
);
```

---

## Navigation

**Previous:** [06-integration.md](06-integration.md)  
**Next:** [08-implementation-roadmap.md](08-implementation-roadmap.md)
