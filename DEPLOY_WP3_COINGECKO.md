# 🚀 Deploy WP3 - Worker CoinGecko

## 📋 Resumo das Mudanças

| Arquivo | Ação | Descrição |
|---------|------|-----------|
| `workers/fetch_prices.php` | ✏️ Reescrito | Batch request otimizado, retry, logging |
| `database/migrations/006_wp3_token_prices_improvements.sql` | ➕ Criado | Adicionar `volume_24h` e `source` |

---

## 🔧 Deploy Manual (Recomendado)

### Passo 1: Upload do Worker via SCP

No **PowerShell** ou **CMD** do Windows:

```powershell
# Navegar até o projeto
cd C:\projetos\main

# Upload do worker
scp -P 22 workers/fetch_prices.php coinup66@coinup.com.br:/home2/coinup66/public_html/main/workers/fetch_prices.php

# Upload da migration
scp -P 22 database/migrations/006_wp3_token_prices_improvements.sql coinup66@coinup.com.br:/home2/coinup66/public_html/main/database/migrations/006_wp3_token_prices_improvements.sql
```

---

### Passo 2: Conectar via SSH e Executar Migration

```bash
# Conectar
ssh coinup66@coinup.com.br

# Navegar
cd /home2/coinup66/public_html/main

# Executar migration
mysql -u coinup66_usuario -p -h localhost coinup66_coinup < database/migrations/006_wp3_token_prices_improvements.sql

# Verificar tabela
mysql -u coinup66_usuario -p -h localhost coinup66_coinup -e "DESCRIBE token_prices;"
```

**Senha:** Digite a senha do banco de dados

---

### Passo 3: Testar Worker

```bash
# Executar worker em modo verbose
php workers/fetch_prices.php --verbose
```

**Output esperado:**
```
╔══════════════════════════════════════════════════════════╗
║         COINGECKO PRICE FETCHER - v2.0                ║
╚══════════════════════════════════════════════════════════╝

[2026-04-10 14:30:00] [INFO] Iniciando busca de preços para 14 tokens...
[2026-04-10 14:30:00] [INFO] Enviando batch request para CoinGecko...
[2026-04-10 14:30:02] [INFO] Batch request concluído. Processando 14 tokens...
[2026-04-10 14:30:02] [INFO] Processando ETH (ethereum)...
  ✓ ETH: USD $3,250.50 | BRL R$ 16,252.50 | 24h 2.35%
  ...

╔══════════════════════════════════════════════════════════╗
║                  RELATÓRIO FINAL                       ║
╚══════════════════════════════════════════════════════════╝

  Início:       2026-04-10 14:30:00
  Duração:      2.45s
  Tokens total:  14
  ✓ Atualizados: 14
  ⚠ Pulados:     0
  ✗ Erros:       0

  Taxa de sucesso: 100.0%
```

---

### Passo 4: Verificar no Banco

```bash
# Conectar ao MySQL
mysql -u coinup66_usuario -p -h localhost coinup66_coinup

# Verificar preços
SELECT token_symbol, price_usd, price_brl, change_24h, last_updated 
FROM token_prices 
ORDER BY token_symbol;

# Verificar se volume_24h existe
DESCRIBE token_prices;
```

**Output esperado:**
```
+-----------------+----------------+----------------+-----------+---------------------+
| token_symbol    | price_usd      | price_brl      | change_24h| last_updated        |
+-----------------+----------------+----------------+-----------+---------------------+
| AAVE            | 285.500000     | 1427.500000    | 3.2500    | 2026-04-10 14:30:00 |
| ARB             | 1.850000       | 9.250000       | 1.5000    | 2026-04-10 14:30:00 |
| BNB             | 580.250000     | 2901.250000    | 0.8500    | 2026-04-10 14:30:00 |
| ...
+-----------------+----------------+----------------+-----------+---------------------+
```

---

### Passo 5: Configurar Cron Job (cPanel)

1. Acesse: `https://coinup.com.br:2083`
2. Vá em **Cron Jobs**
3. Adicionar novo Cron Job:

**Configuração:**
```
Minute:   */15
Hour:     *
Day:      *
Month:    *
Weekday:  *

Command:  php /home2/coinup66/public_html/main/workers/fetch_prices.php
```

**Ou copie e cole:**
```bash
*/15 * * * * php /home2/coinup66/public_html/main/workers/fetch_prices.php
```

4. Clique em **Add New Cron Job**

---

## ✅ Checklist de Deploy

- [ ] Upload do `fetch_prices.php` via SCP
- [ ] Upload da migration `006_wp3_token_prices_improvements.sql`
- [ ] Migration executada no banco
- [ ] Worker testado com `--verbose`
- [ ] Verificado no banco (preços atualizados)
- [ ] Cron Job configurado no cPanel
- [ ] Log verificado: `logs/fetch_prices.log`

---

## 🆘 Troubleshooting

### Erro: `COINGECKO_API_KEY não configurada`

**Solução:** Verifique `.env` no servidor:
```bash
cat /home2/coinup66/public_html/main/.env | grep COINGECKO
```

Se não aparecer, adicione:
```bash
echo "COINGECKO_API_KEY=CG-15VXrwawQF3SABRTqk4yATFM" >> /home2/coinup66/public_html/main/.env
```

---

### Erro: `Column 'volume_24h' not found`

**Solução:** Migration não foi executada:
```bash
mysql -u coinup66_usuario -p -h localhost coinup66_coinup < database/migrations/006_wp3_token_prices_improvements.sql
```

---

### Erro: `Rate limit (429)`

**Solução:** Aguarde 1 minuto e tente novamente. O worker já tem retry automático.

Se persistir, verifique o plano da CoinGecko:
- Free: 10-50 req/min
- Pro: 100 req/min

Nosso worker faz **1 req a cada 15 min**, então está bem dentro do limite.

---

### Erro: `Connection refused` no MySQL

**Solução:** Verifique credenciais no `.env`:
```bash
cat /home2/coinup66/public_html/main/.env | grep DB_
```

---

## 📊 Monitoramento

### Ver último update de preços:
```bash
mysql -u coinup66_usuario -p -h localhost coinup66_coinup -e \
  "SELECT token_symbol, price_usd, last_updated FROM token_prices ORDER BY last_updated DESC LIMIT 5;"
```

### Ver logs do worker:
```bash
tail -50 /home2/coinup66/public_html/main/logs/fetch_prices.log
```

### Ver erros recentes:
```bash
grep ERROR /home2/coinup66/public_html/main/logs/fetch_prices.log | tail -10
```

---

## 🎯 Melhorias do v2.0

| Feature | v1.0 | v2.0 |
|---------|------|------|
| Chamadas API | 8 (uma por token) | 1 (batch) |
| Economia de API | - | **87% menos** |
| Retry automático | ❌ | ✅ (3x, backoff exponencial) |
| Logging em arquivo | ❌ | ✅ |
| Validação de dados | ❌ | ✅ (outliers, zeros, stablecoins) |
| Rate limit handling | ❌ | ✅ (429 retry) |
| Volume 24h | ❌ | ✅ |
| Dry run mode | ❌ | ✅ (`--dry-run`) |
|Verbose mode | ❌ | ✅ (`--verbose`) |
| Tokens DeFi | 8 | 14 |

---

## 📝 Próximo Passo

Após deploy do worker de preços, prosseguir para:

1. **Worker Benchmarks** (SP500, Ouro, CDI, etc.)
2. **Cálculo de Preço Médio (DCA)**
3. **Cálculo de P&L**

---

**Deploy Date:** 2026-04-10
**Version:** WP3 Worker CoinGecko v2.0
