# Instruções para Executar Migration 004 (WP2 Infra Fix)

## O que este migration corrige:

1. ✅ **sync_state** - Adiciona UNIQUE KEY composto `(wallet_id, network)` para suportar múltiplas redes por carteira
2. ✅ **transactions_cache** - Garante existência das colunas `usd_value_at_tx` e `raw_data`
3. ✅ **transactions_cache** - Adiciona valor `'defi'` ao ENUM `transaction_type`
4. ✅ **sync_logs** - Garante existência das colunas `blocks_processed`, `transactions_found`, `duration_seconds`
5. ✅ **wallets** - Garante existência das colunas `last_sync_attempt` e `sync_error_count`
6. ✅ **Índices de performance** - Adiciona índices otimizados para queries de sync

## Como Executar:

### Opção 1: Via phpMyAdmin (Recomendado)

1. Acesse o phpMyAdmin: `https://coinup.com.br:2083/cpsess{session}/3rdparty/phpMyAdmin/`
2. Selecione o banco: `coinup66_coinup`
3. Clique na aba **SQL**
4. Copie e cole o conteúdo do arquivo `004_wp2_infra_fix.sql`
5. Clique em **Executar**
6. Verifique a mensagem de sucesso

### Opção 2: Via SSH/CLI

```bash
cd /home2/coinup66/public_html/main
mysql -u coinup66_usuario -p -h localhost coinup66_coinup < database/migrations/004_wp2_infra_fix.sql
```

### Opção 3: Via Script Web (Se disponível)

Se você tem o script `fix-passwords.php` como modelo:
1. Crie um script `run-migration-004.php` em `public/`
2. Execute via browser: `https://coinup.com.br/main/public/run-migration-004.php`
3. **Remova o script após executar** (segurança)

## Verificação Pós-Execução:

Após executar, verifique no phpMyAdmin:

### 1. sync_state - Deve ter UNIQUE KEY composto
```sql
SHOW INDEX FROM sync_state;
-- Deve aparecer: unique_wallet_network (wallet_id, network)
```

### 2. transactions_cache - Deve ter coluna transaction_type com 'defi'
```sql
SHOW COLUMNS FROM transactions_cache LIKE 'transaction_type';
-- Deve incluir: 'defi' no ENUM
```

### 3. sync_logs - Deve ter todas as colunas
```sql
SHOW COLUMNS FROM sync_logs;
-- Deve incluir: blocks_processed, transactions_found, duration_seconds
```

### 4. wallets - Deve ter campos de sync
```sql
SHOW COLUMNS FROM wallets LIKE '%sync%';
-- Deve aparecer: last_sync_attempt, sync_error_count
```

### 5. Índices criados
```sql
-- Verificar índices em transactions_cache
SHOW INDEX FROM transactions_cache;
-- Deve incluir: idx_tx_hash

-- Verificar índices em sync_state
SHOW INDEX FROM sync_state;
-- Deve incluir: idx_network_block

-- Verificar índices em wallets
SHOW INDEX FROM wallets;
-- Deve incluir: idx_active_sync
```

## Bug Fix no SyncService.php:

Além do migration, o arquivo `src/Services/SyncService.php` foi corrigido:

- ❌ **Antes:** `ORDER BY w.last_sync_attempt ASC NULLS FIRST` (sintaxe PostgreSQL, não funciona no MySQL)
- ✅ **Depois:** `ORDER BY COALESCE(w.last_sync_attempt, '1970-01-01') ASC` (sintaxe MySQL compatível)

## Próximos Passos:

Após executar este migration:
1. Testar sync manual via admin: `https://coinup.com.br/main/public/sync-manual.php`
2. Verificar logs de sync: `https://coinup.com.br/main/public/admin-logs.php`
3. Configurar Cron Job no cPanel para `workers/sync_blockchain.php`

---

**Data:** Abril 2026
**Responsável:** WP2 Infraestrutura
