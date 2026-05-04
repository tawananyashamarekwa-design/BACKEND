CREATE TABLE IF NOT EXISTS users (
    id SERIAL PRIMARY KEY,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    firstName VARCHAR(100) NOT NULL,
    lastName VARCHAR(100) NOT NULL,
    role VARCHAR(50) DEFAULT 'customer',
    isActive BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_users_email ON users (email);
CREATE INDEX IF NOT EXISTS idx_users_role ON users (role);

CREATE TABLE IF NOT EXISTS categories (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    icon VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_categories_name ON categories (name);

CREATE TABLE IF NOT EXISTS products (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    price DECIMAL(10, 2) NOT NULL,
    stockQuantity INT DEFAULT 0,
    categoryId INT REFERENCES categories(id),
    sku VARCHAR(100) UNIQUE,
    image VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_products_category ON products (categoryId);
CREATE INDEX IF NOT EXISTS idx_products_name ON products (name);
CREATE INDEX IF NOT EXISTS idx_products_price ON products (price);
CREATE INDEX IF NOT EXISTS idx_products_sku ON products (sku);

CREATE TABLE IF NOT EXISTS orders (
    id SERIAL PRIMARY KEY,
    userId INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    orderNumber VARCHAR(50) UNIQUE NOT NULL,
    totalAmount DECIMAL(10, 2) NOT NULL,
    status VARCHAR(50) DEFAULT 'processing',
    payment_method VARCHAR(50),
    payment_status VARCHAR(50) DEFAULT 'Pending',
    paynow_poll_url TEXT,
    paynow_reference VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_orders_user ON orders (userId);
CREATE INDEX IF NOT EXISTS idx_orders_status ON orders (status);
CREATE INDEX IF NOT EXISTS idx_orders_payment_status ON orders (payment_status);
CREATE INDEX IF NOT EXISTS idx_orders_orderNumber ON orders (orderNumber);

CREATE TABLE IF NOT EXISTS order_items (
    id SERIAL PRIMARY KEY,
    orderId INT NOT NULL REFERENCES orders(id) ON DELETE CASCADE,
    productId INT NOT NULL REFERENCES products(id),
    quantity INT NOT NULL,
    unitPrice DECIMAL(10, 2) NOT NULL,
    subtotal DECIMAL(10, 2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_order_items_order ON order_items (orderId);
CREATE INDEX IF NOT EXISTS idx_order_items_product ON order_items (productId);

CREATE TABLE IF NOT EXISTS payments (
    id SERIAL PRIMARY KEY,
    orderId INT NOT NULL UNIQUE REFERENCES orders(id) ON DELETE CASCADE,
    amount DECIMAL(10, 2) NOT NULL,
    paymentMethod VARCHAR(50),
    status VARCHAR(50) DEFAULT 'pending',
    transactionId VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_payments_status ON payments (status);
CREATE INDEX IF NOT EXISTS idx_payments_transactionId ON payments (transactionId);

INSERT INTO categories (name, description) VALUES
('Laptops', 'Portable computers and notebooks'),
('Phones', 'Mobile phones and smartphones'),
('Accessories', 'Electronic accessories and peripherals')
ON CONFLICT (name) DO NOTHING;

INSERT INTO users (email, password, firstName, lastName, role, isActive) VALUES
('admin@electronics.local', '$2y$10$N9qo8uLOickgx2ZMRZoMyeIjZAgcg7b3XeKeUxWdeS86E36gZvWFm', 'Admin', 'User', 'admin', TRUE),
('customer@example.com', '$2y$10$n7cNSdxYaBoOz1Y/UpPG8eJMJeA9TzL7pTfpTSILqHNwuT7X4BZ4K', 'John', 'Doe', 'customer', TRUE)
ON CONFLICT (email) DO NOTHING;

INSERT INTO products (name, description, price, stockQuantity, categoryId, sku, image) VALUES
('HP Pavilion 15', 'Everyday laptop for study, work, and entertainment.', 780.00, 8, (SELECT id FROM categories WHERE name = 'Laptops'), 'HP-PAV-15', 'https://ssl-product-images.www8-hp.com/digmedialib/prodimg/lowres/c08346584.png'),
('Dell Inspiron 15', 'Reliable laptop with practical performance for daily tasks.', 720.00, 7, (SELECT id FROM categories WHERE name = 'Laptops'), 'DELL-INS-15', 'https://i.dell.com/is/image/DellContent/content/dam/ss2/product-images/page/category/laptop/inspiron-family-hero-504x350.png'),
('Samsung Galaxy A15', 'Android smartphone with a large display and long battery life.', 250.00, 20, (SELECT id FROM categories WHERE name = 'Phones'), 'SAM-A15', 'https://images.samsung.com/is/image/samsung/p6pim/levant/sm-a155flbimeb/gallery/levant-galaxy-a15-sm-a155-sm-a155flbimeb-thumb-539790141'),
('Apple iPhone 13', 'Apple smartphone with A15 Bionic performance and dual cameras.', 650.00, 10, (SELECT id FROM categories WHERE name = 'Phones'), 'APL-IP13', 'https://store.storeimages.cdn-apple.com/4982/as-images.apple.com/is/iphone-13-finish-select-202207-6-1inch-blue'),
('Sony Wireless Headphones', 'Comfortable wireless headphones with clear sound.', 120.00, 15, (SELECT id FROM categories WHERE name = 'Accessories'), 'SNY-WH-01', 'https://www.sony.com/image/5d02da5df552836db894cead8a68f5f3')
ON CONFLICT (sku) DO NOTHING;
