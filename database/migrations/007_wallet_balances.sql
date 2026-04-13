-- ============================================
-- Migration 007: Tabela wallet_balances
-- Descrição: Armazena saldos atuais das carteiras (buscados direto da blockchain)
-- Motivo: Dashboard e Assets usavam cálculo de transações (impreciso)
-- Data: 2026-04-13
-- ============================================

CREATE TABLE IF NOT EXISTS wallet_balances (
    id INT AUTO_INCREMENT PRIMARY KEY,
    wallet_id INT NOT NULL,
    token_address VARCHAR(42) DEFAULT NULL COMMENT 'NULL para token nativo (ETH, BNB)',
    token_symbol VARCHAR(20) NOT NULL,
    token_name VARCHAR(100) DEFAULT NULL,
    balance DECIMAL(65, 18) NOT NULL DEFAULT 0,
    balance_usd DECIMAL(20, 8) DEFAULT NULL,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (wallet_id) REFERENCES wallets(id) ON DELETE CASCADE,
    UNIQUE KEY unique_wallet_token (wallet_id, token_address, token_symbol),
    INDEX idx_wallet (wallet_id),
    INDEX idx_updated (last_updated)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Índice composto para busca rápida por usuário
CREATE INDEX idx_user_balances ON wallet_balances(wallet_id, balance_usd);
