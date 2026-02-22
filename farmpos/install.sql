-- ============================================================
-- Baffa Precision Agri-Tech — Poultry, Eggs & Aquaculture POS
-- Database Installation Script
-- Run this in phpMyAdmin, then visit /setup_admin.php
-- ============================================================

CREATE DATABASE IF NOT EXISTS farmpos CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE farmpos;

-- ─── ADMIN (single user) ──────────────────────────────────
CREATE TABLE IF NOT EXISTS admin (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    business_name VARCHAR(150) DEFAULT 'Baffa Precision Agri-Tech',
    business_address TEXT,
    business_phone VARCHAR(30),
    business_logo VARCHAR(255),
    currency VARCHAR(10) DEFAULT 'NGN',
    currency_symbol VARCHAR(5) DEFAULT '₦',
    tax_rate DECIMAL(5,2) DEFAULT 0.00,
    receipt_footer TEXT,
    last_login DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ─── PRODUCT CATEGORIES ───────────────────────────────────
CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(80) NOT NULL,
    color VARCHAR(20) DEFAULT '#0a84ff',
    icon VARCHAR(10) DEFAULT '📦',
    sort_order INT DEFAULT 0
);

-- ─── PRODUCTS ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT,
    name VARCHAR(150) NOT NULL,
    sku VARCHAR(50) UNIQUE,
    barcode VARCHAR(80),
    unit VARCHAR(20) DEFAULT 'piece',
    price_retail DECIMAL(12,2) NOT NULL DEFAULT 0,
    price_wholesale DECIMAL(12,2) NOT NULL DEFAULT 0,
    cost_price DECIMAL(12,2) DEFAULT 0,
    stock_qty DECIMAL(12,2) DEFAULT 0,
    reorder_level DECIMAL(12,2) DEFAULT 5,
    track_stock TINYINT(1) DEFAULT 1,
    is_active TINYINT(1) DEFAULT 1,
    image VARCHAR(255),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
);

-- ─── PRODUCT VARIANTS (egg size, fish type) ───────────────
CREATE TABLE IF NOT EXISTS product_variants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    variant_name VARCHAR(80) NOT NULL,
    price_retail DECIMAL(12,2) DEFAULT 0,
    price_wholesale DECIMAL(12,2) DEFAULT 0,
    stock_qty DECIMAL(12,2) DEFAULT 0,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

-- ─── SUPPLIERS ────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS suppliers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    phone VARCHAR(30),
    email VARCHAR(100),
    address TEXT,
    balance DECIMAL(12,2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ─── CUSTOMERS ────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS customers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    phone VARCHAR(30),
    email VARCHAR(100),
    address TEXT,
    type ENUM('retail','wholesale') DEFAULT 'retail',
    loyalty_points INT DEFAULT 0,
    balance DECIMAL(12,2) DEFAULT 0,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ─── SALES ────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS sales (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_no VARCHAR(30) NOT NULL UNIQUE,
    customer_id INT,
    subtotal DECIMAL(12,2) DEFAULT 0,
    discount_amount DECIMAL(12,2) DEFAULT 0,
    tax_amount DECIMAL(12,2) DEFAULT 0,
    total DECIMAL(12,2) NOT NULL DEFAULT 0,
    paid_amount DECIMAL(12,2) DEFAULT 0,
    change_amount DECIMAL(12,2) DEFAULT 0,
    balance_due DECIMAL(12,2) DEFAULT 0,
    payment_method ENUM('cash','card','mobile_money','split','credit') DEFAULT 'cash',
    payment_ref VARCHAR(100),
    status ENUM('completed','held','voided','partial') DEFAULT 'completed',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS sale_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sale_id INT NOT NULL,
    product_id INT,
    variant_id INT,
    product_name VARCHAR(200) NOT NULL,
    unit VARCHAR(20) DEFAULT 'piece',
    qty DECIMAL(12,2) NOT NULL,
    unit_price DECIMAL(12,2) NOT NULL,
    cost_price DECIMAL(12,2) DEFAULT 0,
    discount DECIMAL(12,2) DEFAULT 0,
    total DECIMAL(12,2) NOT NULL,
    FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
);

-- Held (draft) carts
CREATE TABLE IF NOT EXISTS held_sales (
    id INT AUTO_INCREMENT PRIMARY KEY,
    label VARCHAR(100),
    cart_data JSON,
    customer_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ─── PURCHASES / STOCK IN ─────────────────────────────────
CREATE TABLE IF NOT EXISTS purchases (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reference VARCHAR(50),
    supplier_id INT,
    total DECIMAL(12,2) DEFAULT 0,
    paid DECIMAL(12,2) DEFAULT 0,
    status ENUM('received','partial','pending') DEFAULT 'received',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS purchase_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    purchase_id INT NOT NULL,
    product_id INT,
    product_name VARCHAR(200),
    qty DECIMAL(12,2) NOT NULL,
    unit_cost DECIMAL(12,2) NOT NULL,
    total DECIMAL(12,2) NOT NULL,
    FOREIGN KEY (purchase_id) REFERENCES purchases(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
);

-- ─── INVENTORY ADJUSTMENTS ────────────────────────────────
CREATE TABLE IF NOT EXISTS inventory_adjustments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT,
    qty_before DECIMAL(12,2),
    qty_change DECIMAL(12,2),
    qty_after DECIMAL(12,2),
    type ENUM('add','remove','damage','correction') DEFAULT 'correction',
    reason VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
);

-- ─── EXPENSES ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS expenses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category VARCHAR(80) NOT NULL,
    description VARCHAR(255),
    amount DECIMAL(12,2) NOT NULL,
    paid_to VARCHAR(150),
    expense_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ─── FARM: FLOCKS ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS flocks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    house VARCHAR(80),
    breed VARCHAR(100),
    source VARCHAR(100),
    start_date DATE,
    initial_count INT DEFAULT 0,
    current_count INT DEFAULT 0,
    status ENUM('active','closed','sold') DEFAULT 'active',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ─── FARM: PRODUCTION LOG ─────────────────────────────────
CREATE TABLE IF NOT EXISTS production_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    flock_id INT NOT NULL,
    log_date DATE NOT NULL,
    eggs_grade_a INT DEFAULT 0,
    eggs_grade_b INT DEFAULT 0,
    eggs_cracked INT DEFAULT 0,
    eggs_dirty INT DEFAULT 0,
    total_collected INT DEFAULT 0,
    sellable INT DEFAULT 0,
    notes TEXT,
    UNIQUE KEY flock_date (flock_id, log_date),
    FOREIGN KEY (flock_id) REFERENCES flocks(id) ON DELETE CASCADE
);

-- ─── FARM: FEED ISSUANCE ─────────────────────────────────
CREATE TABLE IF NOT EXISTS feed_issuance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    flock_id INT NOT NULL,
    issue_date DATE NOT NULL,
    feed_type VARCHAR(80) NOT NULL,
    qty_kg DECIMAL(10,2) NOT NULL,
    unit_cost DECIMAL(10,2) DEFAULT 0,
    total_cost DECIMAL(12,2) DEFAULT 0,
    notes TEXT,
    FOREIGN KEY (flock_id) REFERENCES flocks(id) ON DELETE CASCADE
);

-- ─── FARM: MORTALITY ─────────────────────────────────────
CREATE TABLE IF NOT EXISTS mortality_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    flock_id INT NOT NULL,
    record_date DATE NOT NULL,
    count INT DEFAULT 0,
    cause VARCHAR(150),
    notes TEXT,
    FOREIGN KEY (flock_id) REFERENCES flocks(id) ON DELETE CASCADE
);

-- ─── RETURNS / REFUNDS ────────────────────────────────────
CREATE TABLE IF NOT EXISTS returns (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sale_id INT,
    product_id INT,
    product_name VARCHAR(200),
    qty DECIMAL(12,2),
    refund_amount DECIMAL(12,2),
    reason VARCHAR(255),
    restock TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ─── AUDIT LOG ───────────────────────────────────────────
CREATE TABLE IF NOT EXISTS audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    action VARCHAR(100),
    description TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ─────────────────────────────────────────────────────────
-- DEFAULT DATA
-- ─────────────────────────────────────────────────────────

INSERT IGNORE INTO categories (name, color, icon, sort_order) VALUES
    ('Eggs',        '#F59E0B', '🥚', 1),
    ('Fish',        '#0EA5E9', '🐟', 2),
    ('Poultry',     '#EF4444', '🐔', 3),
    ('Feed & Input','#10B981', '🌾', 4),
    ('Packaged',    '#8B5CF6', '📦', 5),
    ('Other',       '#6B7280', '🏷️', 6);

INSERT IGNORE INTO customers (name, phone, type) VALUES ('Walk-in Customer', NULL, 'retail');

INSERT IGNORE INTO suppliers (name, phone) VALUES ('Default Supplier', NULL);

-- NOTE: After running this, visit http://localhost/farmpos/setup_admin.php
