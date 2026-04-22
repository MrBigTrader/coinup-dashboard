-- ============================================================
-- Migration 008: Security & Performance Fixes
-- Date: 2026-04-22
-- Description: 
--   1. Add UNIQUE INDEX on transactions_cache (wallet_id, tx_hash) 
--      to support INSERT IGNORE for idempotent sync
--   2. Add UNIQUE INDEX on benchmarks (symbol, date)
--      to support historical series accumulation
-- ============================================================

-- 1. UNIQUE INDEX for INSERT IGNORE idempotency in sync
-- Check if index exists before creating
SET @idx_exists = (
    SELECT COUNT(1) FROM INFORMATION_SCHEMA.STATISTICS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'transactions_cache' 
    AND (INDEX_NAME = 'unique_wallet_tx' OR INDEX_NAME = 'unique_tx')
);

SET @sql = IF(@idx_exists = 0,
    'ALTER TABLE transactions_cache ADD UNIQUE INDEX unique_wallet_tx (wallet_id, tx_hash)',
    'SELECT "Index unique_tx already exists" AS info'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 2. UNIQUE INDEX for benchmark historical series  
SET @idx_exists2 = (
    SELECT COUNT(1) FROM INFORMATION_SCHEMA.STATISTICS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'benchmarks' 
    AND INDEX_NAME = 'unique_benchmark_date'
);

SET @sql2 = IF(@idx_exists2 = 0,
    'ALTER TABLE benchmarks ADD UNIQUE INDEX unique_benchmark_date (symbol, date)',
    'SELECT "Index unique_benchmark_date already exists" AS info'
);
PREPARE stmt2 FROM @sql2;
EXECUTE stmt2;
DEALLOCATE PREPARE stmt2;

-- 3. Ensure price_history has UNIQUE INDEX for daily snapshots
SET @idx_exists3 = (
    SELECT COUNT(1) FROM INFORMATION_SCHEMA.STATISTICS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'price_history' 
    AND INDEX_NAME = 'unique_token_date'
);

SET @sql3 = IF(@idx_exists3 = 0,
    'ALTER TABLE price_history ADD UNIQUE INDEX unique_token_date (token_symbol, recorded_at)',
    'SELECT "Index unique_token_date already exists" AS info'
);
PREPARE stmt3 FROM @sql3;
EXECUTE stmt3;
DEALLOCATE PREPARE stmt3;
