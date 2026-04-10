# 🪙 CoinUp Dashboard

**Dashboard de Acompanhamento de Investimentos DeFi**

Versão: 1.2 (WP1 Concluído)
Data: Abril 2026
Site: coinup.com.br
Status: WP1 ✅ | WP2 ⏳

---

## 📋 Visão Geral

O CoinUp é uma plataforma para acompanhamento de portfólio DeFi em múltiplas redes EVM, com:

- **5 Redes Suportadas:** Ethereum, BNB Chain, Arbitrum, Base, Polygon
- **Cadastro Híbrido de Carteiras:** Web3/MetaMask + Manual
- **Engine DCA:** Cálculo de preço médio de compra (WP2)
- **Dashboard do Cliente:** Visão completa de patrimônio (USD/BRL), assets, transações e benchmarks
- **Painel Admin:** Gestão de clientes, carteiras e sincronização

---

## 🏗️ Arquitetura

### Stack Tecnológico

| Componente | Tecnologia |
|------------|-----------|
| Backend | PHP 7.4+ |
| Banco de Dados | MySQL 5.7+ |
| Frontend | HTML5, CSS3, JavaScript |
| Gráficos | Chart.js |
| Blockchain | Alchemy API |
| Preços | CoinGecko, Alpha Vantage |
| Hospedagem | HostGator Shared Hosting |

### Estrutura de Diretórios

```
main/
├── public/                 # Arquivos acessíveis publicamente
│   ├── login.php          # Página de login
│   ├── dashboard.php      # Dashboard do cliente
│   ├── admin.php          # Painel do administrador
│   ├── assets.php         # Tela de assets
│   ├── transactions.php   # Tela de transações
│   ├── market.php         # Tela de mercado
│   ├── logout.php         # Logout
│   ├── error.php          # Página de erro
│   └── .htaccess          # Configurações Apache
├── config/                # Configurações do sistema
│   ├── database.php       # Conexão com banco de dados
│   ├── auth.php           # Autenticação e sessão
│   └── middleware.php     # Middleware de rotas
├── src/                   # Código fonte (classes, models)
├── workers/               # Scripts de sincronização
│   ├── sync_blockchain.php
│   ├── fetch_prices.php
│   └── fetch_benchmarks.php
├── assets/                # Arquivos estáticos
│   ├── css/
│   ├── js/
│   └── images/
├── database/
│   └── migrations/        # Scripts SQL
│       └── 001_initial_schema.sql
├── logs/                  # Logs do sistema
├── .env                   # Variáveis de ambiente (NÃO COMMITAR)
├── .env.example           # Modelo de variáveis de ambiente
├── deploy.sh              # Script de deploy SSH
└── README.md              # Este arquivo
```

---

## 🚀 Instalação

### Pré-requisitos

- HostGator Shared Hosting (ou similar com PHP + MySQL)
- Acesso SSH e cPanel
- Contas nas APIs: Alchemy, CoinGecko, Alpha Vantage

### Passo a Passo

#### 1. Banco de Dados (cPanel)

1. Acesse o cPanel: `https://coinup.com.br:2083`
2. Vá em **MySQL® Databases**
3. Crie um banco: `coinup66_coinup`
4. Crie um usuário: `coinup66_usuario` com senha forte
5. Associe usuário ao banco com **TODOS os privilégios**

#### 2. Executar Migration

No phpMyAdmin ou via SSH:

```bash
mysql -u coinup66_usuario -p -h localhost coinup66_coinup < /home2/coinup66/public_html/main/database/migrations/001_initial_schema.sql
```

#### 3. Configurar .env

```bash
cd /home2/coinup66/public_html/main
cp .env.example .env
nano .env
```

Preencha as variáveis:

```env
DB_HOST=localhost
DB_NAME=coinup66_coinup
DB_USER=coinup66_usuario
DB_PASS=sua_senha

ALCHEMY_ETHEREUM_KEY=sua_chave
ALCHEMY_BNB_KEY=sua_chave
ALCHEMY_ARBITRUM_KEY=sua_chave
ALCHEMY_BASE_KEY=sua_chave
ALCHEMY_POLYGON_KEY=sua_chave

COINGECKO_API_KEY=sua_chave
ALPHA_VANTAGE_API_KEY=sua_chave

SESSION_SECRET=string_aleatoria_segura
APP_URL=https://coinup.com.br/main
```

#### 4. Upload dos Arquivos

Via SSH (scp) ou FTP, faça upload de todos os arquivos para:
```
/home2/coinup66/public_html/main/
```

#### 5. Configurar Permissões

```bash
cd /home2/coinup66/public_html/main
chmod 755 public
chmod 700 config
chmod 700 logs
chmod 600 .env
chmod 644 public/*.php
```

#### 6. Corrigir Senhas dos Usuários

Após executar o migration, as senhas dos usuários de teste precisam ser corrigidas.

Acesse: `https://coinup.com.br/main/public/fix-passwords.php`

Este script vai atualizar as senhas para `CoinUp2026!` automaticamente.

#### 7. Testar Acesso

Acesse: `https://coinup.com.br/main/public/login.php`

**Credenciais de teste:**
- Admin: `admin@coinup.com.br` / `CoinUp2026!`
- Cliente: `cliente@coinup.com.br` / `CoinUp2026!`

---

## 🔧 Configuração das APIs

### Alchemy (Blockchain)

1. Crie conta em: https://dashboard.alchemy.com/
2. Crie 5 apps (um por rede)
3. Copie as chaves para o `.env`

**Limites:** 300M CUs/mês (free tier)

### CoinGecko (Preços Cripto)

1. Crie conta em: https://www.coingecko.com/api
2. Obtenha API key (free: 10-50 chamadas/min)

### Alpha Vantage (Benchmarks)

1. Crie conta em: https://www.alphavantage.co/
2. Obtenha API key (free: 25 chamadas/dia)

---

## 📅 Cron Jobs (cPanel)

Configure em **Cron Jobs** no cPanel:

### Sync Blockchain (cada 30 min)
```bash
*/30 * * * * php /home2/coinup66/public_html/main/workers/sync_blockchain.php
```

### Fetch Preços Cripto (cada 15 min)
```bash
*/15 * * * * php /home2/coinup66/public_html/main/workers/fetch_prices.php
```

### Fetch Benchmarks (1x dia)
```bash
0 18 * * * php /home2/coinup66/public_html/main/workers/fetch_benchmarks.php
```

---

## 🧪 Testes

### WP1 - Fundação e Infraestrutura - ✅ TODOS PASSARAM (25/25)

- [x] Login com credenciais válidas redireciona corretamente
- [x] Login inválido exibe erro
- [x] Acesso direto a URL restrita sem sessão redireciona para login
- [x] Admin acessa painel admin
- [x] Cliente acessa dashboard
- [x] Arquivo .env não acessível via browser (teste: `https://coinup.com.br/main/.env` → 403)
- [x] Assets, Transactions, Market carregam sem erro
- [x] CRUD de clientes funciona
- [x] Edição de carteiras funciona
- [x] Adicionar carteira via MetaMask funciona
- [x] Adicionar carteira manual funciona
- [x] Ativar/desativar/remover carteira funciona
- [x] Patrimônio exibido no dashboard e detalhes do cliente

---

## 🔒 Segurança

### Boas Práticas Implementadas

- ✅ Senhas com `password_hash()` (bcrypt)
- ✅ Prepared statements (PDO) contra SQL Injection
- ✅ Sessão segura (httponly, secure, samesite)
- ✅ .htaccess protegendo arquivos sensíveis
- ✅ HTTPS obrigatório
- ✅ CSRF protection (a implementar)
- ✅ XSS protection (htmlspecialchars em todos os outputs)

### Arquivos Protegidos

- `.env` → 403 Forbidden
- `config/` → 403 Forbidden
- `database/` → 403 Forbidden
- `*.sql, *.log` → 403 Forbidden

---

## 📝 Work Packages (WP)

| WP | Status | Descrição | Data | Docs |
|----|--------|-----------|------|------|
| **WP1** | ✅ **Concluído** | **Fundação e Infraestrutura** | 03/04/2026 | `WP1_RESUMO.md` |
| WP2 | 🟡 **Em Progresso** | Engine de Sincronização Blockchain | 06/04/2026 | - |
| WP3 | ⚪ Pendente | Engine de Preços e Benchmarks | - | - |
| WP4 | ⚪ Pendente | Dashboard do Cliente (avançado) | - | - |
| WP5 | ⚪ Pendente | Painel do Administrador (avançado) | - | - |
| WP6 | ⚪ Pendente | Finalização, Segurança e Deploy | - | - |

### WP1 - Resumo das Entregas

**Autenticação:** Login/logout seguro, sessão com regeneração de ID, redirecionamento por perfil.

**Dashboard do Cliente:** Overview com patrimônio (USD/BRL), assets, transactions, market, minhas carteiras.

**Cadastro de Carteiras EVM:** Modelo híbrido (Web3/MetaMask + manual), validação de endereço, 5 redes suportadas.

**Painel Admin:** CRUD de clientes, gestão de carteiras, status de sync, logs, patrimônio por cliente.

**Segurança:** .htaccess, headers, prepared statements, bcrypt, HTTPS forçado.

**Banco de Dados:** 9 tabelas, migration completo, dados de teste.

**Testes:** 25/25 passaram (100%).

**Documentação:** `WP1_RESUMO.md`, `CORRECAO_SENHAS.md`.

---

## 🆘 Troubleshooting

### Erro de Conexão com Banco

```
Erro: SQLSTATE[HY000] [2002] Connection refused
```

**Solução:** Verifique credenciais no `.env` e se o banco existe.

### Página em Branco

**Solução:** Verifique logs em `/home2/coinup66/public_html/main/logs/`

### .env Não Acessível

Teste: `https://coinup.com.br/main/.env` deve retornar **403 Forbidden**

Se retornar 400 ou conteúdo, verifique se `.htaccess` está sendo lido.

### Senhas de Teste Não Funcionam

**Solução:** Acesse `https://coinup.com.br/main/public/fix-passwords.php` para corrigir.

### Carteiras Não Aparecem

**Solução:** Verifique se as tabelas existem via `debug-wallets.php`.

---

## 📞 Suporte

**Documentação:** coinup.com.br/docs
**Email:** suporte@coinup.com.br
**Changelog:** `CHANGELOG.md`
**Especificação Técnica:** `CoinUp_Especificacao_Tecnica_v1.md`

---

## 📄 Licença

© 2026 CoinUp. Todos os direitos reservados.
