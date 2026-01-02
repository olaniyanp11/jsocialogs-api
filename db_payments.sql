-- Add payment_reference to orders table
-- Note: Run this only if columns don't exist (remove IF NOT EXISTS if your MySQL version doesn't support it)
ALTER TABLE orders 
ADD COLUMN payment_reference VARCHAR(255) DEFAULT NULL,
ADD COLUMN payment_status ENUM('pending', 'success', 'failed') DEFAULT 'pending';

-- Add index for payment_reference
ALTER TABLE orders ADD INDEX idx_payment_reference (payment_reference);

-- Optional: Create payments table for more detailed payment tracking
CREATE TABLE IF NOT EXISTS payments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id INT UNSIGNED NOT NULL,
    payment_reference VARCHAR(255) NOT NULL UNIQUE,
    amount DECIMAL(10,2) NOT NULL,
    currency VARCHAR(3) DEFAULT 'NGN',
    status ENUM('pending', 'success', 'failed') NOT NULL DEFAULT 'pending',
    paystack_response JSON DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    INDEX idx_payment_reference (payment_reference),
    INDEX idx_order_id (order_id),
    INDEX idx_status (status)
);

