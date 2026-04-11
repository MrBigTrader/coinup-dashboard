# 🔍 Code Review - Tasks Pendentes

**Data:** 10 de Abril de 2026
**Projeto:** CoinUp Dashboard v1.3
**Commit:** af2055f (Initial commit - WP1+WP2 completos)
**Arquivos revisados:** 56
**Linhas de código:** 10.398

---

## 📊 Resumo

| Severidade | Quantidade | Status |
|---|---|---|
| 🔴 Critical | 7 | ⏳ Pendente |
| 🟠 High | 3 | ⏳ Pendente |
| 🟡 Medium | 4 | ⏳ Pendente |
| 💬 Suggestion | 3 | ⏳ Pendente |

**Veredicto:** 🔴 **REQUEST CHANGES** - Não prosseguir para WP3 sem corrigir críticos

---

## 🔴 CRITICAL - Deve corrigir ANTES de prosseguir

### 1. `.env` com credenciais reais no repositório
- **File:** `.env` (commitado)
- **Issue:** Database password, API keys (Alchemy, CoinGecko, Alpha Vantage, Explorer) commitadas
- **Impact:** Credenciais de produção comprometidas
- **Ação:**
  - [ ] Rotacionar TODAS as credenciais expostas (HostGator, Alchemy, CoinGecko, Alpha Vantage, Explorer)
  - [ ] Criar `.env.example` com valores placeholder
  - [ ] Garantir `.env` no `.gitignore` (já está)
  - [ ] Remover `.env` do histórico git (opcional: `git filter-branch` ou `git filter-repo`)
- **Severity:** 🔴 Critical

---

### 2. `fix-passwords.php` acessível sem autenticação
- **File:** `public/fix-passwords.php`:43
- **Issue:** Script reseta TODAS as senhas para `CoinUp2026!` sem verificação de auth
- **Impact:** Qualquer visitante pode tomar controle de qualquer conta
- **Ação:**
  - [ ] **REMOVER este arquivo de produção**
  - [ ] Se necessário para emergências, proteger com token hardcoded + IP whitelist
  - [ ] Documentar procedimento de emergência em `README.md`
- **Severity:** 🔴 Critical

---

### 3. `debug-env.php` expõe secrets no browser
- **File:** `public/debug-env.php`:50
- **Issue:** Exibe conteúdo completo do `.env` como HTML
- **Impact:** Vazamento de todos os secrets via histórico do browser/cache
- **Ação:**
  - [ ] **REMOVER este arquivo de produção**
  - [ ] Se necessário para debugging, mascarar valores (mostrar apenas primeiros 3 chars + `...`)
  - [ ] Restringir acesso por IP ou token adicional
- **Severity:** 🔴 Critical

---

### 4. SSRF via `file_get_contents` nos workers
- **File:** `workers/fetch_benchmarks.php`:213-220
- **Issue:** `file_get_contents($url)` com URL de variáveis de ambiente sem validação
- **Impact:** SSRF para serviços internos se `.env` for comprometido
- **Ação:**
  - [ ] Implementar allowlist de domínios permitidos
  - [ ] Migrar de `file_get_contents` para cURL com timeout explícito
```php
$allowed_domains = ['api.bcb.gov.br', 'www.alphavantage.co', 'finance.yahoo.com'];
$parsed = parse_url($url);
if (!in_array($parsed['host'], $allowed_domains)) {
    throw new InvalidArgumentException("Domínio não permitido: {$parsed['host']}");
}
```
- **Severity:** 🔴 Critical

---

### 5. N+1 Query no `saveTransaction()`
- **File:** `src/Services/SyncService.php`:196-199
- **Issue:** Para cada transação: 1 SELECT + 1 INSERT. 500 transações = 1000 queries sequenciais
- **Impact:** Sync extremamente lento, risco de timeout em shared hosting
- **Ação:**
  - [ ] Criar Migration 006: adicionar `UNIQUE INDEX unique_tx (wallet_id, tx_hash)`
  - [ ] Refatorar `saveTransaction()` para usar `INSERT IGNORE`
  - [ ] Testar idempotência (executar sync 2x sem duplicar)
```sql
-- Migration 006
ALTER TABLE transactions_cache
ADD UNIQUE INDEX unique_tx (wallet_id, tx_hash);
```
```php
// Usar INSERT IGNORE
$stmt = $this->db->prepare("
    INSERT IGNORE INTO transactions_cache (
        wallet_id, tx_hash, block_number, timestamp, ...
    ) VALUES (?, ?, ?, ?, ...)
");
```
- **Severity:** 🔴 Critical

---

### 6. INSERT individual sem batch no sync
- **File:** `src/Services/SyncService.php`:196-242
- **Issue:** Cada transação faz um `execute()` individual. 500 transações = 500 round-trips MySQL
- **Impact:** Performance drasticamente reduzida
- **Ação:**
  - [ ] Implementar batch insert de 100 em 100 transações
  - [ ] Testar performance antes/depois
  - [ ] Verificar que não há perda de dados
```php
$batch = [];
foreach ($allTransfers as $transfer) {
    // ... parse ...
    $batch[] = $txData;
    if (count($batch) >= 100) {
        $this->insertBatch($batch);
        $batch = [];
    }
}
if (!empty($batch)) $this->insertBatch($batch);
```
- **Severity:** 🔴 Critical

---

### 7. Dashboard calcula patrimônio com JOIN massivo sem cache
- **File:** `public/dashboard.php`:46-60
- **Issue:** A cada page load, faz JOIN de `wallets` × `transactions_cache` × `token_prices`. Sem cache
- **Impact:** Timeouts em shared hosting, dashboard lento
- **Ação:**
  - [ ] Criar tabela `wallet_balances` (migration 007)
  - [ ] Implementar cálculo no worker de sync (atualizar após cada sync)
  - [ ] Refatorar dashboard para ler de `wallet_balances`
  - [ ] Implementar recálculo on-demand em `admin.php`
```sql
CREATE TABLE wallet_balances (
    wallet_id INT PRIMARY KEY,
    total_value_usd DECIMAL(20,8),
    total_value_brl DECIMAL(20,8),
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```
- **Severity:** 🔴 Critical

---

## 🟠 HIGH - Recomendado corrigir

### 8. Sem CSRF protection em endpoints AJAX
- **File:** `public/api/add-wallet.php`, `delete-wallet.php`, `toggle-wallet.php`
- **Issue:** Endpoints state-changing protegidos apenas por sessão. Vulnerável a CSRF
- **Impact:** Adicionar/remover carteiras via página maliciosa
- **Ação:**
  - [ ] Implementar geração de token CSRF em `config/auth.php`
  - [ ] Adicionar verificação CSRF em todos os endpoints AJAX
  - [ ] Incluir token CSRF em todos os forms e requests fetch
```php
// config/auth.php
function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}
```
- **Severity:** 🟠 High

---

### 9. `reset-sync.php` via GET com destruição de dados
- **File:** `public/reset-sync.php`:31-33
- **Issue:** Ação destrutiva via GET `?wallet_id=X&confirm=yes`. Sem CSRF
- **Impact:** Resetar sync embeddando imagem/link malicioso
- **Ação:**
  - [ ] Mudar para POST com CSRF token
  - [ ] Adicionar confirmação explícita com token único
  - [ ] Logar ação no `sync_logs`
```php
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die('Método não permitido');
}
if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    die('CSRF validation failed');
}
```
- **Severity:** 🟠 High

---

### 10. Erros de banco expostos ao cliente
- **File:** `public/api/add-wallet.php`:107, `delete-wallet.php`:42-44
- **Issue:** `$e->getMessage()` expõe detalhes do banco (schema, tabelas, SQL)
- **Impact:** Information disclosure facilita recon para ataques
- **Ação:**
  - [ ] Criar função helper para tratamento de erros
  - [ ] Logar erros detalhados server-side
  - [ ] Retornar mensagem genérica ao cliente
```php
// Helper function
function handle_api_error(Exception $e, string $context): void {
    error_log("Erro em {$context}: " . $e->getMessage());
    error_log("Stack: " . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro interno. Tente novamente.'
    ]);
}
```
- **Severity:** 🟠 High

---

## 🟡 MEDIUM - Melhorias importantes

### 11. Race condition no `saveTransaction()`
- **File:** `src/Services/SyncService.php`:137-143
- **Issue:** Check-then-insert (SELECT depois INSERT) não é atômico
- **Impact:** Duplicação de transações sob concorrência
- **Ação:**
  - [ ] Resolvido junto com Task #5 (INSERT IGNORE + UNIQUE INDEX)
- **Severity:** 🟡 Medium
- **Dependência:** Task #5

---

### 12. `hexdec()` perde precisão para valores grandes
- **File:** `src/Services/TransactionParser.php`:176
- **Issue:** `hexdec()` retorna float para valores > `PHP_INT_MAX` (~9.2×10¹⁸). Tokens ERC-20 com 18 decimais excedem
- **Impact:** Valores incorretos para transações grandes
- **Ação:**
  - [ ] Verificar se extensão `gmp` ou `bcmath` está disponível no servidor
  - [ ] Implementar conversão precisa com GMP/BCMath
  - [ ] Testar com valores grandes de transações reais
```php
private static function hexToDecimal(string $hex, int $decimals): string {
    if (empty($hex) || $hex === '0x') return '0';
    $hex = str_replace('0x', '', $hex);
    $wei = gmp_strval(gmp_init($hex, 16));
    return WeiConverter::weiToDecimal($wei, $decimals);
}
```
- **Severity:** 🟡 Medium

---

### 13. `putenv()` deprecated e não thread-safe
- **File:** `config/database.php`:32-33
- **Issue:** `putenv()` não é thread-safe e deprecated em PHP 7.3+
- **Impact:** Credentials podem aparecer como faltantes intermitentemente
- **Ação:**
  - [ ] Substituir `putenv()` por `$_ENV[$name] = $value`
  - [ ] Criar função helper `env($key, $default = null)`
  - [ ] Considerar usar `vlucas/phpdotenv` via Composer
```php
function env($key, $default = null) {
    $value = $_ENV[$key] ?? getenv($key);
    if ($value === false) return $default;
    return $value;
}
```
- **Severity:** 🟡 Medium

---

### 14. `resync-all-wallets.php` sem rate limiting
- **File:** `public/resync-all-wallets.php`:33
- **Issue:** Trigger de sync completo para TODOS wallets via browser. Sem rate limit
- **Impact:** Esgotar quotas da Alchemy API ou causar DoS
- **Ação:**
  - [ ] Mover para CLI-only OU
  - [ ] Adicionar cooldown de 1 hora entre execuções
  - [ ] Exigir confirmação com token
```php
$last_resync = $_SESSION['last_resync'] ?? 0;
if (time() - $last_resync < 3600) {
    $remaining = 3600 - (time() - $last_resync);
    die("Aguarde " . floor($remaining / 60) . " minutos entre resyncs");
}
$_SESSION['last_resync'] = time();
```
- **Severity:** 🟡 Medium

---

## 💬 SUGGESTION / NICE TO HAVE

### 15. `decodeString` sem validação de comprimento
- **File:** `src/Blockchain/AlchemyClient.php`:177
- **Issue:** Não valida comprimento antes de `substr`, pode corromper tokens não-padrão
- **Ação:**
  - [ ] Adicionar validação: `if (strlen($hexData) < 128 + $length * 2) return '';`
  - [ ] Validar retorno de `hex2bin()`
- **Severity:** 💬 Suggestion

---

### 16. Credenciais de teste hardcoded no login
- **File:** `public/login.php`:231-236
- **Issue:** Email e senha de teste visíveis no HTML para qualquer visitante
- **Ação:**
  - [ ] Mostrar credenciais apenas em `APP_ENV=development`
  - [ ] Remover credenciais de teste em produção
```php
<?php if (getenv('APP_ENV') === 'development'): ?>
<div class="test-credentials">
    <p>Admin: admin@coinup.com.br / CoinUp2026!</p>
</div>
<?php endif; ?>
```
- **Severity:** 💬 Suggestion

---

### 17. `fetch_prices.php` faz requisições sequenciais
- **File:** `workers/fetch_prices.php`:86-97
- **Issue:** 8 tokens = 8 requisições HTTP sequenciais (~8s). CoinGecko suporta batch
- **Ação:**
  - [ ] Implementar batch request com múltiplos IDs
  - [ ] Testar se CoinGecko free tier permite multi-IDs
```php
$ids = implode(',', array_column($tokens, 'coingecko_id'));
$url = "$base_url/simple/price?ids=$ids&vs_currencies=usd,brl";
// 1 requisição retorna todos os preços
```
- **Severity:** 💬 Suggestion

---

## 📋 Próximos Passos

### Ordem recomendada de execução:

**FASE 1 - Emergência (FAZER AGORA):**
1. [ ] Task #1 - Rotacionar credenciais expostas
2. [ ] Task #2 - Remover `fix-passwords.php`
3. [ ] Task #3 - Remover `debug-env.php`

**FASE 2 - Segurança (FAZER ANTES DE TUDO):**
4. [ ] Task #4 - SSRF validation nos workers
5. [ ] Task #8 - CSRF protection
6. [ ] Task #9 - POST + CSRF em reset-sync
7. [ ] Task #10 - Error handling seguro
8. [ ] Task #14 - Rate limiting em resync

**FASE 3 - Performance (FAZER ANTES DO WP3):**
9. [ ] Task #5 - INSERT IGNORE + UNIQUE INDEX
10. [ ] Task #6 - Batch insert no sync
11. [ ] Task #7 - Cache de patrimônio (wallet_balances)
12. [ ] Task #17 - Batch fetch prices

**FASE 4 - Qualidade (FAZER QUANDO POSSÍVEL):**
13. [ ] Task #11 - Race condition (resolvido com #5)
14. [ ] Task #12 - GMP/BCMath para hexToDecimal
15. [ ] Task #13 - Substituir putenv()
16. [ ] Task #15 - Validação decodeString
17. [ ] Task #16 - Credenciais de teste condicionais

---

## 🎯 Critérios para Prosseguir para WP3

Somente iniciar WP3 quando:

- [ ] Todas as tasks **CRITICAL** estiverem ✅ concluídas
- [ ] Todas as tasks **HIGH** estiverem ✅ concluídas
- [ ] Tasks **MEDIUM** #11 e #12 estiverem ✅ concluídas
- [ ] Tests de sync passarem (idempotência, performance, sem duplicação)
- [ ] CSRF funcionar em todos os endpoints
- [ ] Dashboard carregar em <2s com dados reais

---

## 📝 Notas

- **Task #11** será resolvida automaticamente com **Task #5**
- Tasks #5, #6 e #7 são **pré-requisitos para WP3** (engine de preços depende de sync performático)
- Credenciais rotacionadas devem ser **atualizadas no `.env` de produção** e no **cPanel**
- Após correções, **re-executar code review** para verificar se não há regressão

---

**Gerado por:** Code Review Agents (Security + Performance + Quality + Audit)
**Data de geração:** 10/04/2026
**Status:** ⏳ Aguardando execução
