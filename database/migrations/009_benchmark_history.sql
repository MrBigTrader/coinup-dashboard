-- ============================================================
-- Migration 009: Benchmark History Table
-- Date: 2026-04-22
-- Description: Separate table for benchmark historical series,
--   since the benchmarks table uses UNIQUE(symbol) for current values.
-- ============================================================

CREATE TABLE IF NOT EXISTS benchmark_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    symbol VARCHAR(20) NOT NULL,
    name VARCHAR(100) NOT NULL,
    value DECIMAL(30, 12) NOT NULL,
    change_24h DECIMAL(10, 4) NULL,
    currency ENUM('USD', 'BRL') NOT NULL,
    source VARCHAR(50) NULL,
    date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_benchmark_date (symbol, date),
    INDEX idx_symbol (symbol),
    INDEX idx_date (date),
    INDEX idx_currency_date (currency, date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Historical daily snapshots of benchmark values for chart comparison';
