-- CoinUp Dashboard - Migration 005 (Correção Coluna defi_protocol)
-- Data: Abril 2026
-- Motivo: Adicionar coluna defi_protocol caso esteja faltando na tabela transactions_cache

SET @dbname = DATABASE();
SET @tablename = 'transactions_cache';
SET @columnname = 'defi_protocol';

-- Verificar se a coluna já existe
SET @preparedStatement = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = @dbname
     AND TABLE_NAME = @tablename
     AND COLUMN_NAME = @columnname) > 0,
    'SELECT 1',
    CONCAT('ALTER TABLE ', @tablename, ' ADD COLUMN ', @columnname, ' VARCHAR(50) NULL COMMENT \'Protocolo DeFi envolvido (ex: uniswap, aave)\' AFTER transaction_type')
));

PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Confirmação
SELECT 'Migration 005 executada. Coluna defi_protocol verificada/adicionada.' AS status;
