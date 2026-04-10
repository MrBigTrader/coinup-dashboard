-- CoinUp Dashboard - Migration 003 (Safe Version)
-- Adição de campos faltantes no modelo de dados
-- Data: Abril 2026
-- Motivo: Divergências encontradas entre especificação técnica e implementação do WP1
-- NOTA: Este script verifica se colunas/tabelas existem antes de criar

-- --------------------------------------------
-- 1. transactions_cache - Campos para P&L e reprocessamento
-- --------------------------------------------
-- Verificar e adicionar usd_value_at_tx
SET @dbname = DATABASE();
SET @tablename = 'transactions_cache';
SET @columnname = 'usd_value_at_tx';
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
   WHERE TABLE_SCHEMA = @dbname
   AND TABLE_NAME = @tablename
   AND COLUMN_NAME = @columnname) > 0,
  'SELECT 1',
  CONCAT('ALTER TABLE ', @tablename, ' ADD COLUMN ', @columnname, ' DECIMAL(30, 12) NULL COMMENT \'Valor em USD na data da transação\' AFTER value')
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Verificar e adicionar raw_data
SET @columnname = 'raw_data';
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
   WHERE TABLE_SCHEMA = @dbname
   AND TABLE_NAME = @tablename
   AND COLUMN_NAME = @columnname) > 0,
  'SELECT 1',
  CONCAT('ALTER TABLE ', @tablename, ' ADD COLUMN ', @columnname, ' JSON NULL COMMENT \'Dados brutos da transação (para reprocessamento)\' AFTER status')
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- --------------------------------------------
-- 2. token_prices - Campo de rastreabilidade
-- --------------------------------------------
SET @tablename = 'token_prices';
SET @columnname = 'source';
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
   WHERE TABLE_SCHEMA = @dbname
   AND TABLE_NAME = @tablename
   AND COLUMN_NAME = @columnname) > 0,
  'SELECT 1',
  CONCAT('ALTER TABLE ', @tablename, ' ADD COLUMN ', @columnname, ' VARCHAR(50) NULL COMMENT \'Fonte do preço (CoinGecko, Alpha Vantage, etc)\' AFTER market_cap_usd')
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- --------------------------------------------
-- 3. benchmarks - Campo para série histórica
-- --------------------------------------------
SET @tablename = 'benchmarks';
SET @columnname = 'date';
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
   WHERE TABLE_SCHEMA = @dbname
   AND TABLE_NAME = @tablename
   AND COLUMN_NAME = @columnname) > 0,
  'SELECT 1',
  CONCAT('ALTER TABLE ', @tablename, ' ADD COLUMN ', @columnname, ' DATE NULL COMMENT \'Data da cotação\' AFTER value')
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Preencher datas existentes com a data atual para registros já criados
UPDATE benchmarks SET date = CURDATE() WHERE date IS NULL;

-- --------------------------------------------
-- 4. users - Campo de auditoria
-- --------------------------------------------
SET @tablename = 'users';
SET @columnname = 'last_login';
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
   WHERE TABLE_SCHEMA = @dbname
   AND TABLE_NAME = @tablename
   AND COLUMN_NAME = @columnname) > 0,
  'SELECT 1',
  CONCAT('ALTER TABLE ', @tablename, ' ADD COLUMN ', @columnname, ' TIMESTAMP NULL COMMENT \'Último login do usuário\' AFTER status')
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- --------------------------------------------
-- 5. wallets - Campos de controle de sync
-- --------------------------------------------
SET @tablename = 'wallets';
SET @columnname = 'last_sync_attempt';
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
   WHERE TABLE_SCHEMA = @dbname
   AND TABLE_NAME = @tablename
   AND COLUMN_NAME = @columnname) > 0,
  'SELECT 1',
  CONCAT('ALTER TABLE ', @tablename, ' ADD COLUMN ', @columnname, ' TIMESTAMP NULL COMMENT \'Última tentativa de sincronização\' AFTER updated_at')
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

SET @columnname = 'sync_error_count';
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
   WHERE TABLE_SCHEMA = @dbname
   AND TABLE_NAME = @tablename
   AND COLUMN_NAME = @columnname) > 0,
  'SELECT 1',
  CONCAT('ALTER TABLE ', @tablename, ' ADD COLUMN ', @columnname, ' INT DEFAULT 0 COMMENT \'Contador de erros consecutivos de sync\' AFTER last_sync_attempt')
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- --------------------------------------------
-- 6. Atualizar índices para performance (WP2)
-- --------------------------------------------
-- Criar índices apenas se não existirem
-- idx_wallet_token
SET @indexname = 'idx_wallet_token';
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
   WHERE TABLE_SCHEMA = @dbname
   AND TABLE_NAME = 'transactions_cache'
   AND INDEX_NAME = @indexname) > 0,
  'SELECT 1',
  'CREATE INDEX idx_wallet_token ON transactions_cache(wallet_id, token_symbol)'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- idx_timestamp_token
SET @indexname = 'idx_timestamp_token';
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
   WHERE TABLE_SCHEMA = @dbname
   AND TABLE_NAME = 'transactions_cache'
   AND INDEX_NAME = @indexname) > 0,
  'SELECT 1',
  'CREATE INDEX idx_timestamp_token ON transactions_cache(timestamp, token_symbol)'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- idx_type
SET @indexname = 'idx_type';
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
   WHERE TABLE_SCHEMA = @dbname
   AND TABLE_NAME = 'transactions_cache'
   AND INDEX_NAME = @indexname) > 0,
  'SELECT 1',
  'CREATE INDEX idx_type ON transactions_cache(transaction_type)'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- idx_symbol_time
SET @indexname = 'idx_symbol_time';
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
   WHERE TABLE_SCHEMA = @dbname
   AND TABLE_NAME = 'price_history'
   AND INDEX_NAME = @indexname) > 0,
  'SELECT 1',
  'CREATE INDEX idx_symbol_time ON price_history(token_symbol, recorded_at)'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- --------------------------------------------
-- 7. Tabela de histórico de preços (WP3/4)
-- --------------------------------------------
CREATE TABLE IF NOT EXISTS price_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    token_symbol VARCHAR(20) NOT NULL,
    price_usd DECIMAL(30, 12) NOT NULL,
    price_brl DECIMAL(30, 12) NULL,
    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    source VARCHAR(50) NULL,
    INDEX idx_symbol_time (token_symbol, recorded_at),
    INDEX idx_recorded_at (recorded_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Histórico diário de preços para gráficos de evolução';

-- --------------------------------------------
-- 8. Tabela de aportes DCA (WP3)
-- --------------------------------------------
CREATE TABLE IF NOT EXISTS dca_entries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    wallet_id INT NOT NULL,
    token_symbol VARCHAR(20) NOT NULL,
    amount DECIMAL(65, 18) NOT NULL,
    price_usd_at_tx DECIMAL(30, 12) NOT NULL,
    total_cost_usd DECIMAL(30, 12) NOT NULL,
    transaction_id INT NULL,
    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (wallet_id) REFERENCES wallets(id) ON DELETE CASCADE,
    FOREIGN KEY (transaction_id) REFERENCES transactions_cache(id) ON DELETE SET NULL,
    INDEX idx_wallet_token (wallet_id, token_symbol),
    INDEX idx_recorded_at (recorded_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Registros de aportes para cálculo de DCA';

-- --------------------------------------------
-- Confirmação: Migration 003 concluída
-- --------------------------------------------
