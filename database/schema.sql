-- Crypto Trading Website Database Schema
-- Create database
CREATE DATABASE IF NOT EXISTS crypto_trading_db;
USE crypto_trading_db;

-- Users table
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    phone VARCHAR(20),
    country VARCHAR(50),
    date_of_birth DATE,
    profile_image VARCHAR(255),
    kyc_status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    kyc_documents TEXT,
    email_verified BOOLEAN DEFAULT FALSE,
    email_verification_token VARCHAR(255),
    two_factor_enabled BOOLEAN DEFAULT FALSE,
    two_factor_secret VARCHAR(255),
    account_status ENUM('active', 'suspended', 'deleted') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL
);

-- Admin users table
CREATE TABLE admin_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('super_admin', 'admin', 'support') DEFAULT 'admin',
    permissions TEXT,
    two_factor_enabled BOOLEAN DEFAULT FALSE,
    two_factor_secret VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL
);

-- User wallets table
CREATE TABLE user_wallets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    currency_type ENUM('fiat', 'crypto') NOT NULL,
    currency_code VARCHAR(10) NOT NULL,
    balance DECIMAL(20, 8) DEFAULT 0.00000000,
    locked_balance DECIMAL(20, 8) DEFAULT 0.00000000,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_currency (user_id, currency_code)
);

-- Transactions table
CREATE TABLE transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    transaction_type ENUM('deposit', 'withdrawal', 'trade', 'investment', 'bonus', 'commission') NOT NULL,
    currency_code VARCHAR(10) NOT NULL,
    amount DECIMAL(20, 8) NOT NULL,
    status ENUM('pending', 'approved', 'rejected', 'completed', 'failed') DEFAULT 'pending',
    transaction_hash VARCHAR(255),
    payment_method VARCHAR(50),
    payment_details TEXT,
    admin_notes TEXT,
    processed_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (processed_by) REFERENCES admin_users(id) ON DELETE SET NULL,
    INDEX idx_user_type (user_id, transaction_type),
    INDEX idx_status (status)
);

-- Investment plans table
CREATE TABLE investment_plans (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    minimum_amount DECIMAL(20, 8) NOT NULL,
    maximum_amount DECIMAL(20, 8) NOT NULL,
    roi_percentage DECIMAL(5, 2) NOT NULL,
    duration_days INT NOT NULL,
    currency_code VARCHAR(10) NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- User investments table
CREATE TABLE user_investments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    plan_id INT NOT NULL,
    amount DECIMAL(20, 8) NOT NULL,
    currency_code VARCHAR(10) NOT NULL,
    expected_return DECIMAL(20, 8) NOT NULL,
    current_return DECIMAL(20, 8) DEFAULT 0.00000000,
    status ENUM('active', 'completed', 'cancelled') DEFAULT 'active',
    start_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    end_date TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (plan_id) REFERENCES investment_plans(id) ON DELETE CASCADE,
    INDEX idx_user_status (user_id, status)
);

-- Binary options table
CREATE TABLE binary_options (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    asset_pair VARCHAR(20) NOT NULL,
    option_type ENUM('call', 'put') NOT NULL,
    amount DECIMAL(20, 8) NOT NULL,
    strike_price DECIMAL(20, 8) NOT NULL,
    expiry_time TIMESTAMP NOT NULL,
    payout_percentage DECIMAL(5, 2) NOT NULL,
    result ENUM('win', 'lose', 'pending') DEFAULT 'pending',
    closing_price DECIMAL(20, 8),
    profit_loss DECIMAL(20, 8) DEFAULT 0.00000000,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_expiry (user_id, expiry_time),
    INDEX idx_expiry_result (expiry_time, result)
);

-- Top traders table
CREATE TABLE top_traders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    display_name VARCHAR(100) NOT NULL,
    description TEXT,
    total_followers INT DEFAULT 0,
    total_trades INT DEFAULT 0,
    winning_trades INT DEFAULT 0,
    success_rate DECIMAL(5, 2) DEFAULT 0.00,
    total_roi DECIMAL(10, 2) DEFAULT 0.00,
    commission_rate DECIMAL(5, 2) DEFAULT 10.00,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_success_rate (success_rate),
    INDEX idx_total_roi (total_roi)
);

-- Copy trading table
CREATE TABLE copy_trading (
    id INT AUTO_INCREMENT PRIMARY KEY,
    follower_id INT NOT NULL,
    trader_id INT NOT NULL,
    allocated_amount DECIMAL(20, 8) NOT NULL,
    allocation_percentage DECIMAL(5, 2) NOT NULL,
    status ENUM('active', 'paused', 'stopped') DEFAULT 'active',
    total_profit_loss DECIMAL(20, 8) DEFAULT 0.00000000,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (follower_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (trader_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_follower_trader (follower_id, trader_id),
    INDEX idx_trader_status (trader_id, status)
);

-- Copy trade executions table
CREATE TABLE copy_trade_executions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    copy_trading_id INT NOT NULL,
    original_trade_id INT NOT NULL,
    follower_id INT NOT NULL,
    trader_id INT NOT NULL,
    trade_type ENUM('binary_option', 'crypto_trade') NOT NULL,
    amount DECIMAL(20, 8) NOT NULL,
    profit_loss DECIMAL(20, 8) DEFAULT 0.00000000,
    commission_paid DECIMAL(20, 8) DEFAULT 0.00000000,
    status ENUM('pending', 'completed', 'failed') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (copy_trading_id) REFERENCES copy_trading(id) ON DELETE CASCADE,
    FOREIGN KEY (follower_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (trader_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_copy_trading (copy_trading_id),
    INDEX idx_follower (follower_id)
);

-- Site settings table
CREATE TABLE site_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT,
    setting_type ENUM('string', 'integer', 'float', 'boolean', 'json') DEFAULT 'string',
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Session management table
CREATE TABLE user_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    session_id VARCHAR(255) NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_session_id (session_id),
    INDEX idx_expires (expires_at)
);

-- Login attempts table (for rate limiting)
CREATE TABLE login_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45) NOT NULL,
    email VARCHAR(100),
    attempts_count INT DEFAULT 1,
    last_attempt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    locked_until TIMESTAMP NULL,
    INDEX idx_ip_time (ip_address, last_attempt),
    INDEX idx_email_time (email, last_attempt)
);

-- Email queue table
CREATE TABLE email_queue (
    id INT AUTO_INCREMENT PRIMARY KEY,
    to_email VARCHAR(100) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    body TEXT NOT NULL,
    status ENUM('pending', 'sent', 'failed') DEFAULT 'pending',
    attempts INT DEFAULT 0,
    max_attempts INT DEFAULT 3,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    sent_at TIMESTAMP NULL,
    INDEX idx_status (status),
    INDEX idx_created (created_at)
);

-- Notifications table
CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    type ENUM('info', 'success', 'warning', 'error') DEFAULT 'info',
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_read (user_id, is_read),
    INDEX idx_created (created_at)
);

-- Audit logs table
CREATE TABLE audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    admin_id INT,
    action VARCHAR(100) NOT NULL,
    table_name VARCHAR(50),
    record_id INT,
    old_values TEXT,
    new_values TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (admin_id) REFERENCES admin_users(id) ON DELETE SET NULL,
    INDEX idx_user_action (user_id, action),
    INDEX idx_admin_action (admin_id, action),
    INDEX idx_created (created_at)
);

-- Insert default admin user (password: admin123)
INSERT INTO admin_users (username, email, password_hash, role) VALUES 
('admin', 'admin@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'super_admin');

-- Insert default site settings
INSERT INTO site_settings (setting_key, setting_value, setting_type, description) VALUES 
('site_name', 'CryptoTrade Pro', 'string', 'Website name'),
('site_description', 'Professional Crypto Trading Platform', 'string', 'Website description'),
('site_email', 'info@cryptotrade.com', 'string', 'Contact email'),
('withdrawal_min_amount', '10', 'float', 'Minimum withdrawal amount'),
('withdrawal_max_amount', '50000', 'float', 'Maximum withdrawal amount'),
('deposit_min_amount', '1', 'float', 'Minimum deposit amount'),
('deposit_max_amount', '100000', 'float', 'Maximum deposit amount'),
('binary_min_trade', '1', 'float', 'Minimum binary trade amount'),
('binary_max_trade', '10000', 'float', 'Maximum binary trade amount'),
('binary_payout_percentage', '85', 'float', 'Binary options payout percentage'),
('coingecko_api_key', '', 'string', 'CoinGecko API key'),
('smtp_host', 'smtp.gmail.com', 'string', 'SMTP server host'),
('smtp_port', '587', 'integer', 'SMTP server port'),
('smtp_username', '', 'string', 'SMTP username'),
('smtp_password', '', 'string', 'SMTP password'),
('maintenance_mode', 'false', 'boolean', 'Maintenance mode status');

-- Insert default investment plans
INSERT INTO investment_plans (name, description, minimum_amount, maximum_amount, roi_percentage, duration_days, currency_code) VALUES 
('Starter Plan', 'Perfect for beginners', 100.00000000, 999.99999999, 10.00, 30, 'USD'),
('Professional Plan', 'For experienced traders', 1000.00000000, 9999.99999999, 15.00, 30, 'USD'),
('VIP Plan', 'Maximum returns for VIP members', 10000.00000000, 99999.99999999, 20.00, 30, 'USD');

-- Create indexes for performance
CREATE INDEX idx_users_email ON users(email);
CREATE INDEX idx_users_status ON users(account_status);
CREATE INDEX idx_transactions_user_created ON transactions(user_id, created_at);
CREATE INDEX idx_investments_user_status ON user_investments(user_id, status);
CREATE INDEX idx_binary_user_created ON binary_options(user_id, created_at);
CREATE INDEX idx_wallets_user_currency ON user_wallets(user_id, currency_code);