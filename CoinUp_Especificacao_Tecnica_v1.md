# CoinUp Dashboard
## Especificação Técnica — v1.0
**Domínio:** `https://coinup.com.br/main` | **Data:** Abril 2026

---

## 1. Visão Geral

| Campo | Descrição |
|---|---|
| **Problema** | Consultores de criptomoedas não dispõem de ferramenta gratuita que unifique rastreamento de carteiras Web3, acompanhamento de DCA e comparativo com benchmarks tradicionais em BRL e USD. |
| **Solução** | Dashboard web multi-usuário que lê transações diretamente das blockchains EVM via Alchemy (cache incremental MySQL), calcula P&L por aporte e compara o patrimônio do cliente com indicadores de mercado em USD e BRL. |
| **Usuário-alvo** | Consultor financeiro (perfil Admin) e seus clientes de carteiras Web3 (perfil Cliente), até 20 usuários na v1. |
| **Plataforma** | Aplicação web responsiva, hospedada em `coinup.com.br/main` (HostGator cPanel compartilhado). |
| **Stack** | PHP 8+ (backend), MySQL (banco de dados + cache), JavaScript/Chart.js (frontend), Python via Passenger (workers opcionais), Cron Jobs cPanel. |
| **Deploy** | `/home2/coinup66/public_html/main` — HostGator Shared Hosting |

---

## 2. Funcionalidades Essenciais — v1

### 2.1 Redes EVM Suportadas

- Ethereum Mainnet
- BNB Smart Chain
- Arbitrum One
- Base
- Polygon (Matic)

### 2.2 Tokens Monitorados

- Bitcoin embrulhado: WBTC, cbBTC
- Stablecoins: USDT, USDC, DAI, BUSD e similares por rede
- RWAs Ondo Finance: ASMLon, SLVon, TSMSon e demais tokens da Ondo
- XAUT (Tether Gold)
- Tokens nativos das redes (ETH, BNB, MATIC, etc.)
- Posições DeFi: AAVE, Venus, Morpho, Krystal, Coins.me, P2P.me
- Bridges: Relay, deBridge, PancakeSwap, Uniswap

### 2.3 Engine de DCA

- Leitura automática de transações via Alchemy (5 redes)
- Cache incremental — apenas blocos novos são consultados; histórico fica no MySQL
- Cálculo de preço médio de compra por token ao longo do tempo
- Gráfico: preço do ativo vs. preço médio de compra do cliente
- Gráfico consolidado: aportes em USD vs. valor atual (P&L)
- Lista detalhada: frequência, volume e P&L individual por aporte

### 2.4 Dashboard do Cliente

- **Overview:** patrimônio total em USD, variação percentual, gráfico de evolução
- **Assets:** posições abertas por token e por rede
- **Transactions:** histórico de transações com P&L por aporte
- **Market:** comparativo de rentabilidade com benchmarks

**Benchmarks em USD:** Patrimônio vs BTC, SP500, Ouro (XAU), T-Bills

**Benchmarks em BRL:** Patrimônio vs BTC, CDI, IBOVESPA

### 2.5 Painel do Administrador

- Visão consolidada de todos os clientes com patrimônio total sob gestão
- Acesso ao dashboard completo de cada cliente individualmente
- Cadastro, edição e remoção de clientes
- Gerenciamento de endereços de carteira por cliente (múltiplos endereços/redes)

### 2.6 Fora de Escopo — v1

- Notificações push / e-mail automáticas
- Relatórios exportáveis em PDF
- Integração com exchanges centralizadas (CEX)
- Suporte a redes não-EVM (Solana, Bitcoin nativo, etc.)
- App mobile nativo

---

## 3. Modelo de Dados

### Entidades Principais

| Entidade | Campos principais |
|---|---|
| `users` | id, name, email, password_hash, role (admin\|client), created_at, last_login |
| `wallets` | id, user_id (FK), address, network (eth\|bsc\|arb\|base\|polygon), label, created_at |
| `transactions_cache` | id, wallet_id (FK), tx_hash, block_number, timestamp, token_symbol, token_contract, amount, usd_value_at_tx, type (buy\|sell\|transfer\|defi), raw_data JSON |
| `sync_state` | id, wallet_id (FK), network, last_block_synced, last_sync_at |
| `token_prices` | id, token_symbol, price_usd, price_brl, source, updated_at |
| `benchmarks` | id, name (BTC\|SP500\|GOLD\|TBILLS\|CDI\|IBOVESPA), value, currency (USD\|BRL), date, source |

---

## 4. Integrações Externas

| Serviço | Uso | Plano / Limite |
|---|---|---|
| **Alchemy** | Leitura de transações EVM (5 redes), cache incremental por bloco | Free: 300M CUs/mês (~1,2M requisições) |
| **CoinGecko API** | Preços de tokens cripto em USD e BRL | Free: 30 req/min |
| **Alpha Vantage** | SP500, Ouro (XAU/USD), T-Bills (rendimento) | Free: 25 req/dia |
| **API BCB (Banco Central)** | Taxa CDI e SELIC atualizadas | Gratuita e oficial (sem limite) |
| **Yahoo Finance (unofficial)** | IBOVESPA (^BVSP) | Gratuita via lib (fallback para Alpha Vantage se instável) |

---

## 5. Requisitos Não-Funcionais

- **Performance:** cache incremental MySQL garante que nenhuma página aguarde chamadas ao vivo para a blockchain na carga inicial
- **Segurança:** autenticação por sessão PHP, proteção de rotas por perfil, chaves de API armazenadas em variáveis de ambiente (`.env`), HTTPS obrigatório via SSL/TLS cPanel
- **Escalabilidade:** arquitetura suporta até 20 clientes com Cron Jobs escalonados; expansão futura via upgrade de plano HostGator ou migração para VPS
- **Rate limiting:** Cron Jobs configurados para respeitar os limites de cada API (Alchemy: incremental por bloco; CoinGecko: delay entre requisições; Alpha Vantage: 1x/dia para benchmarks estáticos)
- **Compatibilidade:** layout responsivo testado em Chrome, Firefox e Safari desktop + mobile
- **Identidade visual:** tema escuro com gradiente espacial roxo/azul, glassmorphism nos cards, paleta e logotipo CoinUp conforme mockup aprovado

---

## 6. Critérios Globais de Pronto

Uma funcionalidade só está completa quando:

- [ ] O código está escrito e funcional
- [ ] Os testes definidos para aquele WP passam
- [ ] Não há erros críticos no console / logs do servidor
- [ ] O comportamento foi validado manualmente com dados reais ou de teste
- [ ] Nenhuma chave de API está hardcoded no código-fonte

---

## 7. Pacotes de Trabalho (WPs)

> ⚠️ **INSTRUÇÃO PARA CLAUDE CODE:** Você DEVE completar e testar cada WP integralmente antes de iniciar o próximo. Não avance para o WP seguinte sem confirmar que todos os critérios de sucesso do WP atual foram atendidos. Se um teste falhar, corrija antes de prosseguir.

---

### WP1 — Fundação e Infraestrutura

**Objetivo:** Scaffolding completo do projeto em `/home2/coinup66/public_html/main`, criação do banco de dados MySQL com modelo de dados completo, e sistema de autenticação funcional com dois perfis de acesso (Admin e Cliente).

**Tarefas:**
- [ ] Criar estrutura de diretórios: `/main/public`, `/main/src`, `/main/config`, `/main/workers`, `/main/assets`
- [ ] Criar arquivo `.env` para armazenar todas as chaves de API e credenciais de banco
- [ ] Criar e executar migration SQL com todas as tabelas: `users`, `wallets`, `transactions_cache`, `sync_state`, `token_prices`, `benchmarks`
- [ ] Implementar sistema de autenticação: login, logout, controle de sessão PHP
- [ ] Implementar middleware de proteção de rotas por perfil (admin / cliente)
- [ ] Criar página de login com identidade visual CoinUp (tema escuro, logo)
- [ ] Criar dois usuários de teste no banco: 1 admin + 1 cliente com carteiras de teste
- [ ] Configurar `.htaccess` para roteamento em `/main` e bloqueio de acesso a diretórios sensíveis

**Critérios de Sucesso:**
- [ ] Login com credenciais válidas redireciona para área correta (admin ou cliente)
- [ ] Login com credenciais inválidas exibe mensagem de erro e não autentica
- [ ] Acesso direto a URL restrita sem sessão redireciona para login
- [ ] Admin acessa painel admin; cliente acessa apenas seu dashboard
- [ ] Banco de dados criado com todas as tabelas e relacionamentos corretos
- [ ] Arquivo `.env` não está acessível publicamente (testado via browser)

**Testes a Executar:**
- Testar login/logout para ambos os perfis
- Tentar acessar `/main/admin` sem autenticação — deve redirecionar
- Tentar acessar dashboard de outro cliente como cliente — deve ser bloqueado
- Verificar no phpMyAdmin que todas as tabelas foram criadas corretamente
- Acessar `/.env` pelo browser — deve retornar 403

> ✅ Só avance para o WP2 após todos os critérios acima estarem satisfeitos.

---

### WP2 — Engine de Sincronização Blockchain

**Objetivo:** Integração com Alchemy para leitura incremental de transações EVM nas 5 redes (Ethereum, BNB Chain, Arbitrum, Base, Polygon). Cron Job que detecta apenas transações novas a partir do último bloco lido, identifica tokens e posições DeFi, e armazena no cache MySQL.

**Tarefas:**
- [ ] Criar cliente PHP para Alchemy API (suporte a `eth_getLogs`, `alchemy_getAssetTransfers`, `eth_getBalance`)
- [ ] Implementar lógica de sincronização incremental: consultar `sync_state`, buscar apenas blocos novos, atualizar `last_block_synced` após cada sync
- [ ] Implementar identificação de tokens: nativos (ETH/BNB/MATIC), ERC-20 padrão, tokens RWA Ondo, XAUT, stablecoins
- [ ] Implementar detecção de interações com contratos DeFi conhecidos (AAVE, Venus, Morpho, Krystal, Coins.me, P2P.me, PancakeSwap, Uniswap, Relay, deBridge)
- [ ] Criar Cron Job no cPanel: executar sync a cada 30 minutos para todos os wallets ativos
- [ ] Implementar log de erros de sync em tabela `sync_logs` (wallet_id, status, error_msg, executed_at)
- [ ] Criar script de sync manual para testes (acessível via CLI ou URL protegida)

**Critérios de Sucesso:**
- [ ] Carteira de teste sincronizada nas 5 redes com histórico completo no MySQL
- [ ] Segunda execução do sync não duplica transações (idempotência por `tx_hash`)
- [ ] `last_block_synced` atualizado corretamente após cada execução
- [ ] Tokens nativos, ERC-20 e interações DeFi identificados e categorizados corretamente
- [ ] Cron Job visível e ativo no cPanel, executando sem erros
- [ ] Log de sync registrado em `sync_logs` para cada execução

**Testes a Executar:**
- Executar sync manual para carteira de teste com histórico conhecido — verificar número de txs importadas
- Executar sync duas vezes — verificar que não há duplicatas (COUNT por `tx_hash`)
- Intencionalmente usar API key inválida — verificar que erro é registrado em `sync_logs`
- Verificar no MySQL que `last_block_synced` avança após cada sync
- Aguardar execução do Cron Job e verificar `sync_logs`

> ✅ Só avance para o WP3 após todos os critérios acima estarem satisfeitos.

---

### WP3 — Engine de Preços e Benchmarks

**Objetivo:** Integração com CoinGecko, Alpha Vantage, API do Banco Central e Yahoo Finance para cotações periódicas de tokens e benchmarks. Cálculo de preço médio de compra, P&L por aporte e comparativos de rentabilidade.

**Tarefas:**
- [ ] Implementar worker PHP para CoinGecko: buscar preços em USD e BRL para todos os tokens monitorados
- [ ] Implementar worker para Alpha Vantage: SP500, Ouro (XAU/USD), T-Bills (rendimento)
- [ ] Implementar worker para API BCB: CDI acumulado diário
- [ ] Implementar worker para IBOVESPA via Yahoo Finance (`^BVSP`), com fallback para Alpha Vantage
- [ ] Criar Cron Jobs separados: preços cripto a cada 15min; benchmarks 1x/dia
- [ ] Implementar função de cálculo de preço médio ponderado por token por carteira
- [ ] Implementar cálculo de P&L absoluto e percentual por aporte (preço na data da tx vs. preço atual)
- [ ] Implementar cálculo de rentabilidade do patrimônio vs. cada benchmark desde data do primeiro aporte

**Critérios de Sucesso:**
- [ ] Tabela `token_prices` atualizada automaticamente com preços atuais em USD e BRL
- [ ] Tabela `benchmarks` atualizada diariamente para todos os 6 indicadores
- [ ] Cálculo de preço médio validado manualmente contra histórico de transações de teste
- [ ] P&L por aporte calculado corretamente: positivo quando preço atual > preço na data da compra
- [ ] Comparativo de rentabilidade retorna valores coerentes para todos os benchmarks em USD e BRL

**Testes a Executar:**
- Verificar `token_prices` no MySQL após Cron Job — todos os tokens com preço > 0
- Calcular manualmente o preço médio de uma carteira de teste e comparar com resultado do sistema
- Verificar P&L de uma transação de compra de WBTC: se BTC subiu, P&L deve ser positivo
- Verificar que CDI é retornado pela API BCB com valor diário correto
- Simular falha de uma API e verificar que sistema continua funcionando com último valor em cache

> ✅ Só avance para o WP4 após todos os critérios acima estarem satisfeitos.

---

### WP4 — Dashboard do Cliente

**Objetivo:** Interface visual completa para o cliente, seguindo o mockup CoinUp (tema escuro, glassmorphism, gradiente espacial roxo/azul). Quatro telas: Overview, Assets, Transactions e Market, com gráficos interativos via Chart.js.

**Tarefas:**
- [ ] Implementar layout base: sidebar com navegação (Overview, Assets, Transactions, Market, Settings), header com nome do usuário e saldo total, fundo com gradiente espacial
- [ ] **Tela Overview:** card Total Balance em USD, variação 24h, gráfico de linha de evolução patrimonial (Chart.js), Top Holdings, Recent Activity
- [ ] **Tela Assets:** lista de tokens com ícone, holdings, valor USD, variação 24h; agrupamento por rede; posições DeFi destacadas
- [ ] **Tela Transactions:** lista paginada de aportes com data, token, quantidade, valor na data, valor atual, P&L absoluto e percentual; filtros por token e período
- [ ] **Gráfico DCA:** linha dupla Chart.js mostrando preço do ativo vs. preço médio de compra ao longo do tempo
- [ ] **Gráfico consolidado:** aportes acumulados em USD vs. valor atual do patrimônio (area chart)
- [ ] **Tela Market:** gráfico de comparativo de rentabilidade do patrimônio vs. benchmarks USD e vs. benchmarks BRL (6 indicadores)
- [ ] Garantir layout responsivo (desktop e mobile)
- [ ] Aplicar glassmorphism nos cards (backdrop-filter, bordas semi-transparentes), paleta e tipografia CoinUp

**Critérios de Sucesso:**
- [ ] Todas as 4 telas renderizam dados reais da carteira de teste sem erros
- [ ] Gráfico de evolução patrimonial exibe histórico correto
- [ ] Gráfico DCA mostra preço do ativo vs. preço médio com datas corretas
- [ ] Gráfico consolidado de aportes vs. valor atual está coerente com os dados de transações
- [ ] Comparativo de benchmarks em USD e BRL exibe todos os 6 indicadores corretamente
- [ ] Layout responsivo validado em tela desktop (1280px+) e mobile (375px)
- [ ] Identidade visual consistente com o mockup aprovado

**Testes a Executar:**
- Navegar por todas as 4 telas — sem erros de console
- Comparar valores exibidos no dashboard com cálculos manuais do WP3
- Testar em Chrome desktop e Chrome mobile (DevTools)
- Testar com carteira vazia — deve exibir estado vazio amigável (sem erros)
- Verificar que o cliente não consegue acessar dados de outro cliente pela URL

> ✅ Só avance para o WP5 após todos os critérios acima estarem satisfeitos.

---

### WP5 — Painel do Administrador

**Objetivo:** Interface exclusiva para o consultor com visão consolidada de todos os clientes, acesso ao dashboard individual de cada um, e gerenciamento completo de cadastros e carteiras.

**Tarefas:**
- [ ] Tela principal admin: tabela de todos os clientes com nome, número de wallets, patrimônio total, variação 24h, último sync
- [ ] Card de resumo: total de clientes ativos, patrimônio total sob gestão, variação do portfolio consolidado
- [ ] Botão "Ver Dashboard" por cliente: carregar o dashboard completo daquele cliente na visão do admin (sem trocar de sessão)
- [ ] Tela de cadastro de cliente: nome, email, senha temporária, adicionar múltiplos endereços de wallet por rede
- [ ] Tela de edição de cliente: atualizar dados, adicionar/remover wallets, forçar sync manual
- [ ] Confirmação antes de remover cliente (soft delete: manter histórico)
- [ ] Log de atividade admin: registro de ações realizadas (cadastro, edição, remoção)

**Critérios de Sucesso:**
- [ ] Admin visualiza todos os clientes cadastrados com dados corretos
- [ ] Admin consegue acessar o dashboard completo de cada cliente individualmente
- [ ] Cadastro de novo cliente funciona e dispara sync inicial das wallets
- [ ] Edição de cliente persiste corretamente no banco
- [ ] Remoção de cliente solicita confirmação e executa soft delete
- [ ] Cliente não consegue acessar o painel admin por nenhuma URL direta

**Testes a Executar:**
- Cadastrar novo cliente com 2 wallets em redes diferentes — verificar sync inicial
- Acessar dashboard do cliente pelo painel admin — verificar dados corretos
- Editar email de cliente — verificar persistência
- Remover cliente — verificar que histórico permanece (soft delete) e login é bloqueado
- Tentar acessar `/main/admin` como cliente autenticado — deve retornar 403

> ✅ Só avance para o WP6 após todos os critérios acima estarem satisfeitos.

---

### WP6 — Finalização, Segurança e Deploy

**Objetivo:** Polish final, tratamento de erros e edge cases, hardening de segurança, configuração de produção e validação end-to-end em `coinup.com.br/main`.

**Tarefas:**
- [ ] Tratar edge cases: carteira vazia, token não reconhecido, API offline (exibir último valor em cache com aviso de defasagem)
- [ ] Implementar páginas de erro amigáveis (404, 500, sessão expirada)
- [ ] Revisão de segurança: validação de inputs, proteção contra SQL injection (prepared statements), XSS, CSRF token em formulários
- [ ] Configurar HTTPS obrigatório via `.htaccess` (redirect HTTP → HTTPS)
- [ ] Verificar que nenhuma chave de API aparece em logs, HTML ou JS do frontend
- [ ] Otimizar consultas MySQL: índices em `wallet_id`, `tx_hash`, `token_symbol`, `timestamp`
- [ ] Escrever `README.md` com: estrutura do projeto, variáveis de ambiente necessárias, instruções de deploy, configuração dos Cron Jobs
- [ ] Executar testes end-to-end completos em produção com dados reais de pelo menos 2 clientes
- [ ] Configurar monitoramento básico: log de erros PHP em arquivo rotacionado, alerta de Cron Job falho

**Critérios de Sucesso:**
- [ ] App funciona end-to-end em `coinup.com.br/main` sem erros críticos
- [ ] HTTPS ativo e HTTP redireciona corretamente
- [ ] Todos os edge cases exibem mensagens amigáveis (sem stack traces expostos)
- [ ] README completo e testado por terceiro
- [ ] Varredura de segurança básica sem vulnerabilidades críticas

**Testes a Executar:**
- Acessar `http://coinup.com.br/main` — deve redirecionar para HTTPS
- Desconectar Alchemy (key inválida temporariamente) — verificar que dashboard exibe aviso e último valor em cache
- Submeter formulário com SQL injection básico — verificar que é sanitizado
- Verificar source HTML da página — nenhuma chave de API deve aparecer
- Testar fluxo completo: cadastrar cliente → sincronizar → visualizar dashboard → ver benchmarks
- Testar em 3 browsers: Chrome, Firefox, Safari

> ✅ Projeto concluído. App disponível em `https://coinup.com.br/main`

---

## 8. Notas Adicionais e Riscos

### Decisões de Arquitetura

- **Stack PHP+MySQL** escolhida por compatibilidade nativa com HostGator Shared Hosting, sem necessidade de configuração adicional de Passenger/Node
- **Cache incremental** é a estratégia central de sustentabilidade do plano gratuito da Alchemy: nunca re-leremos blocos já processados
- **Chart.js** escolhido por ser leve, sem dependências de servidor e com suporte nativo a gráficos de linha dupla necessários para o DCA
- **Benchmarks armazenados diariamente no MySQL:** o comparativo de rentabilidade é calculado sobre série histórica local, sem chamadas de API em tempo real no dashboard

### Riscos Identificados

| Risco | Mitigação |
|---|---|
| Yahoo Finance instável (API não-oficial) | Fallback para Alpha Vantage; exibir último valor com data de atualização |
| Alpha Vantage free tier (25 req/dia) | Retry com backoff e cache de 24h; suficiente para 6 benchmarks 1x/dia |
| Alchemy 300M CUs/mês com 20 clientes × 5 redes | Sync apenas de wallets com atividade recente a cada 30min; inativas 1x/dia |
| PHP timeout em Shared Hosting (~30-60s) | Cron Job processa uma wallet por vez em fila, não todas simultaneamente |

### Versões Futuras (fora de escopo v1)

- Exportação de relatórios em PDF por cliente
- Notificações automáticas por e-mail (alertas de P&L, novo aporte detectado)
- Suporte a redes não-EVM (Solana, Bitcoin nativo via explorers)
- Integração com exchanges CEX (Binance, Coinbase) via API
- App mobile nativo (React Native)
- Modo de demonstração pública para atrair novos clientes
