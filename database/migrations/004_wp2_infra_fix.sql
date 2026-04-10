-- CoinUp Dashboard - Migration 004 (Correções de Infra WP2)
-- Data: Abril 2026
-- Motivo: Corrigir problemas de índices e colunas para engine de sincronização

-- --------------------------------------------
-- 1. sync_state - Adicionar UNIQUE KEY composto (wallet_id, network)
--    Necessário para ON DUPLICATE KEY UPDATE funcionar corretamente
--    NOTA: Não podemos remover o índice wallet_id pois é usado pela FOREIGN KEY
--    Solução: Adicionar UNIQUE KEY composto sem remover o existente
-- --------------------------------------------

SET @dbname = DATABASE();
SET @tablename = 'sync_state';
SET @indexname = 'unique_wallet_network';

-- Adicionar UNIQUE KEY composto (wallet_id, network) apenas se não existir
SET @preparedStatement = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
     WHERE TABLE_SCHEMA = @dbname
     AND TABLE_NAME = @tablename
     AND INDEX_NAME = @indexname) > 0,
    'SELECT 1',
    'ALTER TABLE sync_state ADD UNIQUE KEY unique_wallet_network (wallet_id, network)'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- --------------------------------------------
-- 2. transactions_cache - Verificar colunas do migration 003
--    (usd_value_at_tx e raw_data já devem existir, mas vamos garantir)
-- --------------------------------------------
SET @tablename = 'transactions_cache';

-- usd_value_at_tx
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

-- raw_data
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
-- 3. Adicionar coluna transaction_type se não existir
--    (o SyncService tenta inserir, mas o ENUM pode não ter todos os valores)
-- --------------------------------------------
SET @tablename = 'transactions_cache';
SET @columnname = 'transaction_type';

-- Verificar se a coluna existe
SET @columnExists = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @dbname
    AND TABLE_NAME = @tablename
    AND COLUMN_NAME = @columnname
);

-- Se não existir, adicionar
SET @preparedStatement = (SELECT IF(
    @columnExists > 0,
    'SELECT 1',
    'ALTER TABLE transactions_cache ADD COLUMN transaction_type ENUM(\'transfer\', \'swap\', \'deposit\', \'withdraw\', \'bridge\', \'defi\', \'unknown\') DEFAULT \'unknown\' AFTER defi_protocol'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Se existe, modificar o ENUM para incluir 'defi'
SET @preparedStatement = (SELECT IF(
    @columnExists = 0,
    'SELECT 1',
    'ALTER TABLE transactions_cache MODIFY COLUMN transaction_type ENUM(\'transfer\', \'swap\', \'deposit\', \'withdraw\', \'bridge\', \'defi\', \'unknown\') DEFAULT \'unknown\''
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- --------------------------------------------
-- 4. sync_logs - Garantir que todas as colunas necessárias existem
-- --------------------------------------------
SET @tablename = 'sync_logs';

-- blocks_processed
SET @columnname = 'blocks_processed';
SET @preparedStatement = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = @dbname
     AND TABLE_NAME = @tablename
     AND COLUMN_NAME = @columnname) > 0,
    'SELECT 1',
    CONCAT('ALTER TABLE ', @tablename, ' ADD COLUMN ', @columnname, ' INT DEFAULT 0 AFTER error_message')
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- transactions_found
SET @columnname = 'transactions_found';
SET @preparedStatement = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = @dbname
     AND TABLE_NAME = @tablename
     AND COLUMN_NAME = @columnname) > 0,
    'SELECT 1',
    CONCAT('ALTER TABLE ', @tablename, ' ADD COLUMN ', @columnname, ' INT DEFAULT 0 AFTER blocks_processed')
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- duration_seconds
SET @columnname = 'duration_seconds';
SET @preparedStatement = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = @dbname
     AND TABLE_NAME = @tablename
     AND COLUMN_NAME = @columnname) > 0,
    'SELECT 1',
    CONCAT('ALTER TABLE ', @tablename, ' ADD COLUMN ', @columnname, ' INT NULL AFTER transactions_found')
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- --------------------------------------------
-- 5. wallets - Garantir campos de controle de sync
-- --------------------------------------------
SET @tablename = 'wallets';

-- last_sync_attempt
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

-- sync_error_count
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
-- 6. Melhorar índices para performance do WP2
-- --------------------------------------------

-- Índice em transactions_cache por tx_hash (para busca de duplicatas)
SET @indexname = 'idx_tx_hash';
SET @preparedStatement = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
     WHERE TABLE_SCHEMA = @dbname
     AND TABLE_NAME = 'transactions_cache'
     AND INDEX_NAME = @indexname) > 0,
    'SELECT 1',
    'CREATE INDEX idx_tx_hash ON transactions_cache(tx_hash)'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Índice em sync_state por network + last_block_synced (para queries de sync)
SET @indexname = 'idx_network_block';
SET @preparedStatement = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
     WHERE TABLE_SCHEMA = @dbname
     AND TABLE_NAME = 'sync_state'
     AND INDEX_NAME = @indexname) > 0,
    'SELECT 1',
    'CREATE INDEX idx_network_block ON sync_state(network, last_block_synced)'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Índice em wallets para query de sync (is_active + last_sync_attempt)
SET @indexname = 'idx_active_sync';
SET @preparedStatement = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
     WHERE TABLE_SCHEMA = @dbname
     AND TABLE_NAME = 'wallets'
     AND INDEX_NAME = @indexname) > 0,
    'SELECT 1',
    'CREATE INDEX idx_active_sync ON wallets(is_active, last_sync_attempt)'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- --------------------------------------------
-- Confirmação: Migration 004 concluída
-- --------------------------------------------
SELECT 'Migration 004 executada com sucesso! Infraestrutura WP2 corrigida.' AS status;
