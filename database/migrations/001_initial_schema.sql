-- ============================================
-- COINUP DASHBOARD - Migration Inicial
-- WP1: Criação de todas as tabelas
-- ============================================

-- --------------------------------------------
-- Tabela: users (Usuários do sistema)
-- --------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'client') NOT NULL DEFAULT 'client',
    status ENUM('active', 'inactive', 'deleted') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    INDEX idx_email (email),
    INDEX idx_role (role),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------
-- Tabela: wallets (Carteiras por cliente)
-- --------------------------------------------
CREATE TABLE IF NOT EXISTS wallets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    network ENUM('ethereum', 'bnb', 'arbitrum', 'base', 'polygon') NOT NULL,
    address VARCHAR(42) NOT NULL,
    label VARCHAR(100) NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_wallet (user_id, network, address),
    INDEX idx_network (network),
    INDEX idx_address (address)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------
-- Tabela: transactions_cache (Transações EVM)
-- --------------------------------------------
CREATE TABLE IF NOT EXISTS transactions_cache (
    id INT AUTO_INCREMENT PRIMARY KEY,
    wallet_id INT NOT NULL,
    tx_hash VARCHAR(66) NOT NULL,
    block_number BIGINT NOT NULL,
    timestamp BIGINT NOT NULL,
    from_address VARCHAR(42) NOT NULL,
    to_address VARCHAR(42) NOT NULL,
    value DECIMAL(65, 18) DEFAULT 0,
    token_address VARCHAR(42) NULL,
    token_symbol VARCHAR(20) NULL,
    token_name VARCHAR(100) NULL,
    token_decimals INT DEFAULT 18,
    transaction_type ENUM('transfer', 'swap', 'deposit', 'withdraw', 'bridge', 'unknown') DEFAULT 'unknown',
    defi_protocol VARCHAR(50) NULL,
    gas_used BIGINT NULL,
    gas_price BIGINT NULL,
    status ENUM('pending', 'confirmed', 'failed') DEFAULT 'confirmed',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (wallet_id) REFERENCES wallets(id) ON DELETE CASCADE,
    UNIQUE KEY unique_tx (wallet_id, tx_hash),
    INDEX idx_block (block_number),
    INDEX idx_timestamp (timestamp),
    INDEX idx_token (token_symbol)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------
-- Tabela: sync_state (Controle de sincronização)
-- --------------------------------------------
CREATE TABLE IF NOT EXISTS sync_state (
    id INT AUTO_INCREMENT PRIMARY KEY,
    wallet_id INT NOT NULL UNIQUE,
    network VARCHAR(20) NOT NULL,
    last_block_synced BIGINT DEFAULT 0,
    last_sync_at TIMESTAMP NULL,
    sync_status ENUM('idle', 'syncing', 'error') DEFAULT 'idle',
    FOREIGN KEY (wallet_id) REFERENCES wallets(id) ON DELETE CASCADE,
    INDEX idx_network (network),
    INDEX idx_status (sync_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------
-- Tabela: sync_logs (Logs de sincronização)
-- --------------------------------------------
CREATE TABLE IF NOT EXISTS sync_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    wallet_id INT NOT NULL,
    status ENUM('success', 'error', 'partial') NOT NULL,
    error_message TEXT NULL,
    blocks_processed INT DEFAULT 0,
    transactions_found INT DEFAULT 0,
    executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    duration_seconds INT NULL,
    FOREIGN KEY (wallet_id) REFERENCES wallets(id) ON DELETE CASCADE,
    INDEX idx_executed_at (executed_at),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------
-- Tabela: token_prices (Preços de tokens)
-- --------------------------------------------
CREATE TABLE IF NOT EXISTS token_prices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    token_symbol VARCHAR(20) NOT NULL,
    token_name VARCHAR(100) NULL,
    coingecko_id VARCHAR(100) NULL,
    price_usd DECIMAL(30, 12) NULL,
    price_brl DECIMAL(30, 12) NULL,
    change_24h DECIMAL(10, 4) NULL,
    market_cap_usd DECIMAL(30, 0) NULL,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_token (token_symbol),
    INDEX idx_symbol (token_symbol)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------
-- Tabela: benchmarks (Indicadores de mercado)
-- --------------------------------------------
CREATE TABLE IF NOT EXISTS benchmarks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    symbol VARCHAR(20) NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL,
    value DECIMAL(30, 12) NOT NULL,
    change_24h DECIMAL(10, 4) NULL,
    currency ENUM('USD', 'BRL') NOT NULL,
    source VARCHAR(50) NULL,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_symbol (symbol),
    INDEX idx_currency (currency)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------
-- Tabela: admin_logs (Logs de atividade admin)
-- --------------------------------------------
CREATE TABLE IF NOT EXISTS admin_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_user_id INT NOT NULL,
    action VARCHAR(100) NOT NULL,
    target_type VARCHAR(50) NULL,
    target_id INT NULL,
    details JSON NULL,
    ip_address VARCHAR(45) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_action (action),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------
-- Tabela: portfolio_history (Histórico de patrimônio)
-- --------------------------------------------
CREATE TABLE IF NOT EXISTS portfolio_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    date DATE NOT NULL,
    total_value_usd DECIMAL(30, 12) NOT NULL,
    total_value_brl DECIMAL(30, 12) NULL,
    change_24h DECIMAL(10, 4) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_date (user_id, date),
    INDEX idx_date (date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------
-- DADOS INICIAIS - Usuários de Teste
-- --------------------------------------------

-- ATENÇÃO: O hash abaixo é um placeholder.
-- Após executar o migration, rode o script fix-passwords.php
-- para definir a senha correta (CoinUp2026!)
-- URL: https://coinup.com.br/main/public/fix-passwords.php

-- Admin (senha temporária - rode fix-passwords.php para corrigir)
INSERT INTO users (name, email, password_hash, role, status)
VALUES ('Administrador', 'admin@coinup.com.br', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'active')
ON DUPLICATE KEY UPDATE name=name;

-- Cliente de teste (senha temporária - rode fix-passwords.php para corrigir)
INSERT INTO users (name, email, password_hash, role, status)
VALUES ('Cliente Teste', 'cliente@coinup.com.br', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'client', 'active')
ON DUPLICATE KEY UPDATE name=name;

-- --------------------------------------------
-- BENCHMARKS INICIAIS
-- --------------------------------------------

INSERT INTO benchmarks (symbol, name, value, currency, source) VALUES
('BTC', 'Bitcoin', 0, 'USD', 'CoinGecko'),
('SP500', 'S&P 500', 0, 'USD', 'Alpha Vantage'),
('XAU', 'Ouro (Troy Ounce)', 0, 'USD', 'Alpha Vantage'),
('TBILL', 'T-Bills 3 Meses', 0, 'USD', 'Alpha Vantage'),
('CDI', 'CDI Acumulado', 0, 'BRL', 'BCB'),
('IBOV', 'IBOVESPA', 0, 'BRL', 'Yahoo Finance')
ON DUPLICATE KEY UPDATE symbol=symbol;
