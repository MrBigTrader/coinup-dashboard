# 📝 Changelog - CoinUp Dashboard

Todas as mudanças notáveis neste projeto serão documentadas neste arquivo.

O formato é baseado em [Keep a Changelog](https://keepachangelog.com/pt-BR/1.0.0/),
e este projeto adere ao [Versionamento Semântico](https://semver.org/lang/pt-BR/).

---

## [1.3.0] - 2026-04-04 - WP2 (Em Progresso)

### ✅ WP2 - Engine de Sincronização Blockchain (Parcial)

#### Adicionado
- **Classes Base:**
  - `src/Utils/WeiConverter.php` - Conversão Wei ↔ Decimal
  - `src/Blockchain/NetworkConfig.php` - Configurações de 5 redes EVM
  - `src/Blockchain/AlchemyClient.php` - Cliente API Alchemy completo
  - `src/Services/TransactionParser.php` - Parser de transações (nativo + ERC-20)
  - `src/Services/SyncService.php` - Orquestração de sync incremental
- **Workers:**
  - `workers/sync_blockchain.php` - Reescrito com nova engine
  - `public/sync-manual.php` - Interface web para sync manual (admin)
- **Detecção automática:**
  - Transações nativas (ETH/BNB/MATIC)
  - Tokens ERC-20
  - Contratos DeFi (Uniswap, AAVE, Venus, etc.)
  - Bridges (Relay, deBridge, Hop, Across)
- **Tratamento de erros:** Retry automático, rate limit handling, logging

#### Alterado
- `database/migrations/003_add_missing_fields.sql` - Versão segura (idempotente)
- `workers/sync_blockchain.php` - Totalmente reescrito com nova engine

### 🔧 Migration 003 Executada
- Campos adicionados: `usd_value_at_tx`, `raw_data`, `source`, `date`, `last_login`, `last_sync_attempt`, `sync_error_count`
- Novas tabelas: `price_history`, `dca_entries`
- Novos índices: `idx_wallet_token`, `idx_timestamp_token`, `idx_type`, `idx_symbol_time`

---

## [1.2.0] - 2026-04-03

### ✅ WP1 Concluído

#### Adicionado
- **Autenticação completa:** Login/logout com sessão PHP, redirecionamento por perfil
- **Dashboard do Cliente:** Overview, Assets, Transactions, Market
- **Cadastro de Carteiras EVM:** Modelo híbrido (Web3/MetaMask + manual)
  - Conexão via MetaMask com extração automática de endereço
  - Detecção automática de rede pelo chain ID
  - Validação de endereço EVM (regex `0x[40 hex]`)
  - 5 redes suportadas: Ethereum, BNB Chain, Arbitrum, Base, Polygon
- **Gestão de Carteiras:** Ativar/desativar, remover, editar label
- **Painel do Administrador:** CRUD de clientes, gestão de carteiras, status de sync, logs
- **Visualização de Cliente (Admin):** Cards com patrimônio (USD/BRL), carteiras, transações
- **Edição de Carteiras (Admin):** Página `admin-wallet-edit.php` para editar label e status
- **Endpoints AJAX:** `api/add-wallet.php`, `api/delete-wallet.php`, `api/toggle-wallet.php`
- **Workers CLI:** `sync_blockchain.php`, `fetch_prices.php`, `fetch_benchmarks.php`
- **Script de correção de senhas:** `fix-passwords.php`
- **Scripts de debug:** `debug-session.php`, `debug-wallets.php`
- **Estilo principal unificado:** `assets/css/style.css` com tema escuro glassmorphism

#### Corrigido
- Hash de senha incorreto no migration (usando hash de "password" ao invés de "CoinUp2026!")
- Erro 500 em páginas com SQL sem tratamento de erros (try-catch adicionado)
- Configuração de sessão incompatível com HTTP em desenvolvimento
- Tabelas faltantes no banco (`sync_state`, `transactions_cache`, etc.)
- `DECIMAL(78, 18)` excede limite do MySQL → corrigido para `DECIMAL(65, 18)`
- `dirname(__DIR__, 2)` incompatível com PHP antigo → usado `dirname(dirname(__DIR__))`
- Página 404 em `admin-wallet-edit.php` (arquivo não existia)
- Dashboard sem cálculo de patrimônio (USD e BRL)
- Detalhes do cliente sem card de patrimônio

#### Documentação
- `WP1_RESUMO.md`: Status 100%, 28 arquivos PHP, 25 testes (100% aprovação), 9 bugs corrigidos
- `README.md`: Atualizado versão para 1.2, tabela de WPs, testes, troubleshooting
- `CORRECAO_SENHAS.md`: Documentação de correção de senhas

### 🔧 Ajustes no Modelo de Dados (Pré-WP2)
- **Migration 003:** Adição de campos faltantes para alinhamento com especificação técnica
  - `transactions_cache`: `usd_value_at_tx` (P&L), `raw_data` (reprocessamento)
  - `token_prices`: `source` (rastreabilidade)
  - `benchmarks`: `date` (série histórica)
  - `users`: `last_login` (auditoria)
  - `wallets`: `last_sync_attempt`, `sync_error_count` (otimização sync)
  - Novos índices compostos para performance
  - Nova tabela `price_history` (histórico de preços para gráficos)
  - Nova tabela `dca_entries` (registros de aportes para DCA)

### Funcionalidades Extras (além da especificação)
- Cadastro de carteiras via MetaMask/Web3 (espec original previa apenas cadastro manual)
- Ativar/desativar carteiras individualmente
- Edição de carteiras pelo admin
- Páginas de debug para troubleshooting
- Mensagens de sucesso/erro visuais em todas as ações

---

## [1.1.0] - 2026-04-03

### Correções de WP1
- Correção do hash de senha dos usuários de teste
- Adição de tratamento de erros (try-catch) em todas as páginas
- Configuração flexível de sessão (development/production)
- Criação de tabelas faltantes no banco

---

## [1.0.0] - 2026-04-02

### Início do Projeto
- Estrutura de diretórios criada
- Migration inicial (`001_initial_schema.sql`)
- Autenticação básica
- Dashboard skeleton

---

## 📊 Estatísticas do Projeto

| Métrica | WP1 |
|---------|-----|
| Arquivos PHP criados | 28 |
| Linhas de código (aprox.) | ~8.500 |
| Tabelas no banco | 11 (9 originais + 2 novas) |
| Páginas públicas | 12 |
| Páginas admin | 9 |
| Endpoints API | 3 |
| Workers CLI | 3 |
| Testes realizados | 25 |
| Taxa de sucesso | 100% |
| Bugs corrigidos | 9 |

---

## 🔮 Próximos WPs

| WP | Status | Descrição |
|----|--------|-----------|
| WP2 | ⏳ Planejado | Engine de Sincronização Blockchain |
| WP3 | ⚪ Pendente | Engine de Preços e Benchmarks |
| WP4 | ⚪ Pendente | Dashboard do Cliente (avançado) |
| WP5 | ⚪ Pendente | Painel do Administrador (avançado) |
| WP6 | ⚪ Pendente | Finalização, Segurança e Deploy |
