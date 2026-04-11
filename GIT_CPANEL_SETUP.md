# 🚀 Guia de Configuração Git no cPanel

## 📋 Visão Geral

Este guia configura o repositório Git do CoinUp Dashboard para deploy automatizado via **Git Version Control** do cPanel (HostGator).

---

## 🔧 Passo 1: Preparar Repositório Local (Windows)

### 1.1 Verificar estado atual do Git

```powershell
cd C:\projetos\main
git status
```

### 1.2 Garantir que `.env` NÃO está no repositório

```powershell
# Verificar se .env está no .gitignore
git check-ignore .env
# Deve retornar: .env (se estiver ignorado corretamente)
```

### 1.3 Criar repositório remoto (GitHub/GitLab/Bitbucket)

Se ainda não tem remoto configurado:

```powershell
# Exemplo com GitHub (substitua com sua URL real)
git remote add origin https://github.com/seu-usuario/coinup-dashboard.git

# Push inicial
git push -u origin master
```

---

## 🌐 Passo 2: Configurar Git no cPanel

### 2.1 Acessar Git Version Control

1. Login no cPanel: `https://coinup.com.br:2083`
2. Procure por **Git Version Control** na seção **Files**
3. Clique em **Create**

### 2.2 Clonar Repositório

Na tela de criação:

- ✅ Marque **Clone a Repository**
- **Clone URL:** `https://github.com/seu-usuario/coinup-dashboard.git`
  - Ou para SSH: `git@github.com:seu-usuario/coinup-dashboard.git`
- **Repository Path:** `/home2/coinup66/coinup-dashboard`
  - ⚠️ **NÃO** use `/home2/coinup66/public_html/main/` como path do Git
  - O cPanel bloqueia certos diretórios
- **Repository Name:** `coinup-dashboard`

Clique em **Create**.

### 2.3 Configurar SSH (opcional, para repositórios privados)

Se usar SSH e for sua primeira vez:

1. Na primeira conexão, cPanel pedirá para salvar a chave do host
2. Clique em **Save and Continue**
3. Se precisar gerar chave SSH no servidor:
   - Acesse via SSH: `ssh coinup66@coinup.com.br`
   - Execute: `ssh-keygen -t ed25519 -C "coinup@cpanel"`
   - Adicione a chave pública (`~/.ssh/id_ed25519.pub`) no GitHub/GitLab

---

## 📂 Passo 3: Configurar Deploy

### 3.1 Estrutura de Diretórios no Servidor

Após clonar, crie um symlink ou copie os arquivos para o diretório público:

**Via SSH no servidor:**

```bash
# Conectar
ssh coinup66@coinup.com.br

# Navegar
cd /home2/coinup66

# Opção A: Symlink (recomendado)
ln -sfn /home2/coinup66/coinup-dashboard/public_html/main /home2/coinup66/public_html/main

# Opção B: Copiar arquivos (se symlink não funcionar)
cp -R /home2/coinup66/coinup-dashboard/* /home2/coinup66/public_html/main/
```

### 3.2 Configurar Arquivo de Deploy (`.cpanel.yml`)

O arquivo `.cpanel.yml` já está configurado no repositório com as tasks:

- ✅ Criar diretório `logs/`
- ✅ Copiar todos os arquivos para `public_html/main/`
- ✅ Configurar permissões corretas
- ✅ Verificar/criar `.env` se não existir

### 3.3 Executar Deploy Manual (Primeira Vez)

1. No cPanel, vá em **Git Version Control**
2. Clique em **Manage** no repositório `coinup-dashboard`
3. Selecione a aba **Pull or Deploy**
4. Clique em **Deploy HEAD Commit**

---

## 🔄 Passo 4: Workflow de Deploy

### 4.1 Fluxo de Deploy (Após cada commit)

**No Windows (local):**

```powershell
# 1. Fazer commit
git add .
git commit -m "Descrição da mudança"
git push origin master

# 2. Verificar status
git status
```

**No cPanel:**

1. Vá em **Git Version Control** → **Manage** → **Pull or Deploy**
2. Clique em **Update from Remote** (puxa último commit)
3. Clique em **Deploy HEAD Commit** (executa `.cpanel.yml`)

### 4.2 Ou via SSH (mais rápido)

```bash
# Conectar ao servidor
ssh coinup66@coinup.com.br

# Navegar até repositório
cd /home2/coinup66/coinup-dashboard

# Puxar alterações
git pull origin master

# Deploy manual (se .cpanel.yml não cobrir tudo)
cp -R * /home2/coinup66/public_html/main/
cp .htaccess /home2/coinup66/public_html/main/
cp .env.example /home2/coinup66/public_html/main/

# Ajustar permissões
chmod 700 /home2/coinup66/public_html/main/config
chmod 700 /home2/coinup66/public_html/main/logs
chmod 644 /home2/coinup66/public_html/main/public/*.php
```

---

## 🔒 Passo 5: Segurança

### 5.1 Verificar Proteção do `.git`

O cPanel bloqueia acesso público ao `.git` automaticamente. Teste:

```
https://coinup.com.br/main/.git/config
```

Deve retornar **403 Forbidden**.

### 5.2 Configurar `.htaccess` Extra

Se não estiver bloqueado, adicione no `.htaccess` raiz:

```apache
# Bloquear acesso ao .git
<DirectoryMatch "^.*/\.git/">
    Require all denied
</DirectoryMatch>

RedirectMatch 403 /\.git
```

### 5.3 Nunca Commitar `.env`

Verifique:

```powershell
git ls-files | findstr .env
# NÃO deve listar .env (apenas .env.example)
```

---

## 🧪 Passo 6: Testes

### 6.1 Testar Clone no Servidor

```bash
# Via SSH no servidor
cd /home2/coinup66
git clone https://github.com/seu-usuario/coinup-dashboard.git test-clone
ls -la test-clone/
rm -rf test-clone  # Limpar teste
```

### 6.2 Testar Deploy

1. Faça uma mudança pequena no código local
2. Commit e push
3. Deploy via cPanel
4. Verificar se arquivos atualizaram em `/home2/coinup66/public_html/main/`
5. Acessar site e verificar funcionalidade

---

## ⚠️ Limitações e Cuidados

### ❌ O que NÃO fazer:

- **NÃO** crie repositório em `/home2/coinup66/public_html/main/` diretamente
- **NÃO** use espaços ou caracteres especiais no caminho do repositório
- **NÃO** commitar `.env` com credenciais reais
- **NÃO** sobrescreva `.env` durante deploy (o script já protege isso)
- **NÃO** modifique ou exclua o diretório `.git` no servidor

### ✅ Boas Práticas:

- Mantenha `.cpanel.yml` no repositório remoto
- Sempre faça deploy em branch `master` (deploy é `--ff-only`)
- Mantenha trabalho não commitado fora do servidor
- Teste mudanças localmente antes de push
- Use branches para features, merge em `master` após testes

---

## 🆘 Troubleshooting

### Deploy falha com "Working tree is dirty"

**Causa:** Arquivos modificados no servidor que não estão no Git.

**Solução:**
```bash
cd /home2/coinup66/coinup-dashboard
git status
git checkout -- .  # Descartar mudanças locais
git pull origin master
```

### Erro de permissão ao copiar arquivos

**Causa:** Usuário do Git não tem permissão de escrita.

**Solução:** Verifique que está usando o usuário correto (`coinup66`).

### `.env` foi sobrescrito acidentalmente

**Solução:**
```bash
cd /home2/coinup66/public_html/main
cp .env.example .env
# Reconfigurar credenciais manualmente
nano .env
```

### Git não atualiza após deploy

**Causa:** Deploy do cPanel usa `--ff-only`, precisa de rebase se houver commits locais.

**Solução:**
```bash
cd /home2/coinup66/coinup-dashboard
git pull --rebase origin master
```

---

## 📊 Resumo da Estrutura

```
Servidor HostGator:
├── /home2/coinup66/
│   ├── coinup-dashboard/          # ← Repositório Git (NÃO público)
│   │   ├── .git/
│   │   ├── .cpanel.yml            # Configuração de deploy
│   │   ├── .env.example           # Modelo de variáveis
│   │   ├── public/
│   │   ├── config/
│   │   ├── workers/
│   │   └── ...
│   │
│   └── public_html/
│       └── main/                  # ← Diretório público (site real)
│           ├── public/
│           ├── config/
│           ├── .env               # ← Credenciais (NÃO no Git)
│           └── ...
```

---

## 🎯 Próximos Passos

1. ✅ **Configurar repositório remoto** (GitHub/GitLab)
2. ✅ **Clonar no cPanel** via Git Version Control
3. ✅ **Testar deploy** com `.cpanel.yml`
4. ✅ **Verificar permissões** e segurança
5. ⏳ **Executar migrations** pendentes (se houver)
6. ⏳ **Configurar cron jobs** para workers

---

**Última atualização:** 11/04/2026
**Versão:** 1.0
**Responsável:** Setup Git cPanel
