# Instruções para Atualizar Cron Job (WP2 - Otimização)

## 📋 Resumo das Mudanças

O sistema foi otimizado para sincronizar carteiras muito mais rápido:

| Antes | Depois |
|-------|--------|
| Salto de 5.000 blocos | **Salto de 200.000 blocos** (padrão) |
| Modo acelerado: 50.000 blocos | **Modo Turbo: 500.000 blocos** (quando vazio) |
| Cron Job a cada 30 min | **Cron Job a cada 5 min** (recomendado) |
| Tempo estimado: 24h+ | **Tempo estimado: 2-4h** por carteira |

---

## 🔄 Como Atualizar o Cron Job

### Passo 1: Acessar cPanel

1. Acesse: `https://coinup.com.br:2083`
2. Vá em **Cron Jobs**

### Passo 2: Editar o Cron Job Existente

1. Encontre o Cron Job atual do `sync_blockchain.php`
2. Altere o intervalo para: **`*/5 * * * *`** (a cada 5 minutos)
3. Mantenha o comando:
   ```bash
   /usr/local/bin/php /home2/coinup66/public_html/main/workers/sync_blockchain.php >> /home2/coinup66/logs/cron_sync.log 2>&1
   ```
4. Salve.

### Passo 3: Criar Pasta de Logs (se não existir)

Via SSH ou File Manager, crie a pasta:
```
/home2/coinup66/logs/
```

---

## 📊 Como Monitorar

### Opção 1: Via Monitor Web
Acesse: `https://coinup.com.br/main/public/sync-monitor.php`

- Status "ATIVO" deve aparecer após a primeira execução do novo Cron Job.
- As estimativas de tempo (ETA) agora consideram o Modo Turbo (500k blocos/ciclo).

### Opção 2: Via SQL
```sql
SELECT executed_at, status, blocks_processed, transactions_found 
FROM sync_logs 
ORDER BY executed_at DESC 
LIMIT 5;
```

### Opção 3: Via Log File (após SSH habilitado)
```bash
tail -f /home2/coinup66/logs/cron_sync.log
```

---

## ⚡ O que Esperar

Com as novas configurações:

| Cenário | Blocos por Ciclo | Ciclos Necessários | Tempo Estimado |
|---------|------------------|-------------------|----------------|
| Carteira vazia (BNB) | 500.000 | ~70 ciclos | **~6 horas** |
| Carteira com histórico (BNB) | 50.000 | ~700 ciclos | **~2-3 dias** (backfill lento) |
| Carteira recente (últimos 30 dias) | 50.000 | ~10 ciclos | **~50 minutos** |

---

## 🚀 Próximos Passos

1. Faça upload dos arquivos atualizados:
   - `src/Services/SyncService.php`
   - `public/sync-monitor.php`
2. Atualize o Cron Job para rodar a cada 5 minutos.
3. Monitore via `sync-monitor.php`.
4. Aguarde a sincronização completar.

---

**Nota:** O arquivo `ExplorerClient.php` e `test-explorer.php` foram criados para teste, mas como a API gratuita dos explorers só funciona para Ethereum, eles não serão usados para BNB Chain e Base. Podemos removê-los após confirmar que a Alchemy está funcionando bem.

---

**Data:** 07/04/2026
**Responsável:** WP2 Otimização Final
