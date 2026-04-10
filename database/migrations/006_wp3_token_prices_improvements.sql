-- ============================================
-- COINUP DASHBOARD - Migration 006
-- WP3: Melhorias na tabela token_prices
-- ============================================
-- Data: 2026-04-10
-- Descrição:
--   - Adicionar volume_24h (necessário para análise de liquidez)
--   - Adicionar coluna source (rastreabilidade de dados)
--   - Otimizar índices para queries do dashboard
-- ============================================

-- Adicionar volume_24h se não existir
ALTER TABLE token_prices
ADD COLUMN IF NOT EXISTS volume_24h DECIMAL(30, 8) NULL AFTER market_cap_usd,
ADD COLUMN IF NOT EXISTS source VARCHAR(50) DEFAULT 'coingecko' AFTER updated_at;

-- Adicionar índice composto para queries de dashboard
-- Dashboard filtra por símbolo e quer dados recentes
CREATE INDEX IF NOT EXISTS idx_symbol_updated ON token_prices (token_symbol, last_updated DESC);

-- Adicionar índice para source (rastreamento de dados)
CREATE INDEX IF NOT EXISTS idx_source ON token_prices (source);

-- Otimizar índice único para ON DUPLICATE KEY UPDATE
-- Garantir que UNIQUE KEY existe (pode já existir do migration 001)
-- Se já existir unique_token, isso será ignorado
ALTER TABLE token_prices
ADD CONSTRAINT unique_token_symbol UNIQUE (token_symbol);

-- --------------------------------------------
-- Verificação
-- --------------------------------------------

SELECT 'Migration 006 aplicada com sucesso!' as status;

-- Verificar estrutura da tabela
DESCRIBE token_prices;

-- Verificar índices
SHOW INDEX FROM token_prices;
