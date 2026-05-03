
CREATE DATABASE IF NOT EXISTS electronics_ecommerce;
USE electronics_ecommerce;


CREATE TABLE IF NOT EXISTS users (
    -- Primary key
    id INT PRIMARY KEY AUTO_INCREMENT,
    
    -- User credentials
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    
    -- User profile
    firstName VARCHAR(100) NOT NULL,
    lastName VARCHAR(100) NOT NULL,
    
    -- Account settings
    role VARCHAR(50) DEFAULT 'customer',  -- 'customer' or 'admin'
    isActive BOOLEAN DEFAULT 1,
    
    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Indexes for faster queries
    INDEX idx_email (email),
    INDEX idx_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS products (
    -- Primary key
    id INT PRIMARY KEY AUTO_INCREMENT,
    
    -- Product details
    name VARCHAR(255) NOT NULL,
    description TEXT,
    price DECIMAL(10, 2) NOT NULL,
    stockQuantity INT DEFAULT 0,
    
    -- Product organization
    categoryId INT,
    sku VARCHAR(100) UNIQUE,
    image VARCHAR(255) NULL,
    
    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Indexes
    INDEX idx_category (categoryId),
    INDEX idx_name (name),
    INDEX idx_price (price),
    INDEX idx_sku (sku)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS categories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    icon VARCHAR(100) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS orders (
    id INT PRIMARY KEY AUTO_INCREMENT,
    
    -- Customer reference
    userId INT NOT NULL,
    
    -- Order details
    orderNumber VARCHAR(50) UNIQUE NOT NULL,
    totalAmount DECIMAL(10, 2) NOT NULL,
    status VARCHAR(50) DEFAULT 'processing',  -- processing, completed, failed, cancelled
    payment_method VARCHAR(50),
    payment_status VARCHAR(50) DEFAULT 'Pending',
    paynow_poll_url TEXT,
    paynow_reference VARCHAR(100),
    
    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Foreign key
    FOREIGN KEY (userId) REFERENCES users(id) ON DELETE CASCADE,
    
    -- Indexes
    INDEX idx_user (userId),
    INDEX idx_status (status),
    INDEX idx_payment_status (payment_status),
    INDEX idx_orderNumber (orderNumber)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS order_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    
    -- References
    orderId INT NOT NULL,
    productId INT NOT NULL,
    
    -- Item details
    quantity INT NOT NULL,
    unitPrice DECIMAL(10, 2) NOT NULL,
    subtotal DECIMAL(10, 2) NOT NULL,
    
    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    -- Foreign keys
    FOREIGN KEY (orderId) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (productId) REFERENCES products(id),
    
    -- Indexes
    INDEX idx_order (orderId),
    INDEX idx_product (productId)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Payment transaction records

CREATE TABLE IF NOT EXISTS payments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    
    -- References
    orderId INT NOT NULL UNIQUE,
    
    -- Payment details
    amount DECIMAL(10, 2) NOT NULL,
    paymentMethod VARCHAR(50),  -- credit_card, paypal, paynow, etc.
    status VARCHAR(50) DEFAULT 'pending',  -- pending, completed, failed, refunded
    
    -- Payment reference
    transactionId VARCHAR(100),
    
    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Foreign key
    FOREIGN KEY (orderId) REFERENCES orders(id) ON DELETE CASCADE,
    
    -- Indexes
    INDEX idx_status (status),
    INDEX idx_transactionId (transactionId)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Insert sample category
INSERT IGNORE INTO categories (name, description) VALUES 
('Laptops', 'Portable computers and notebooks'),
('Phones', 'Mobile phones and smartphones'),
('Accessories', 'Electronic accessories and peripherals');

-- Insert sample admin user (password: admin123)
INSERT IGNORE INTO users (email, password, firstName, lastName, role, isActive) VALUES 
('admin@electronics.local', '$2y$10$N9qo8uLOickgx2ZMRZoMyeIjZAgcg7b3XeKeUxWdeS86E36gZvWFm', 'Admin', 'User', 'admin', 1);

-- Insert sample customer (password: customer123)
INSERT IGNORE INTO users (email, password, firstName, lastName, role, isActive) VALUES 
('customer@example.com', '$2y$10$n7cNSdxYaBoOz1Y/UpPG8eJMJeA9TzL7pTfpTSILqHNwuT7X4BZ4K', 'John', 'Doe', 'customer', 1);

