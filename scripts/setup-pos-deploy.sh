#!/bin/bash
# ============================================
# COINUP - Setup Pós-Deploy no Servidor
# ============================================
# Execute este script via SSH APÓS o primeiro deploy Git
# Uso: bash setup-pos-deploy.sh

echo "╔══════════════════════════════════════════════════════════╗"
echo "║         COINUP - Setup Pós-Deploy                      ║"
echo "╚══════════════════════════════════════════════════════════╝"
echo ""

# Cores
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Diretórios
REPO_DIR="/home2/coinup66/coinup-dashboard"
PUBLIC_DIR="/home2/coinup66/public_html/main"

# --------------------------------------------
# 1. Verificar se repositório existe
# --------------------------------------------
echo "[1/7] Verificando repositório Git..."

if [ ! -d "$REPO_DIR/.git" ]; then
    echo -e "${RED}✗ Repositório Git não encontrado em $REPO_DIR${NC}"
    echo "Clone o repositório primeiro via cPanel Git Version Control"
    exit 1
fi

echo -e "${GREEN}✓ Repositório Git encontrado${NC}"
echo ""

# --------------------------------------------
# 2. Criar symlink ou copiar arquivos
# --------------------------------------------
echo "[2/7] Configurando diretório público..."

if [ -L "$PUBLIC_DIR" ]; then
    echo -e "${YELLOW}! Symlink já existe, removendo...${NC}"
    rm -f "$PUBLIC_DIR"
fi

if [ -d "$PUBLIC_DIR" ]; then
    echo -e "${YELLOW}! Diretório já existe. Deseja sobrescrever? (s/N)${NC}"
    read -r resp
    if [[ "$resp" =~ ^[Ss]$ ]]; then
        rm -rf "$PUBLIC_DIR"
    else
        echo "Mantendo diretório existente. Usando modo de cópia..."
        cp -R "$REPO_DIR"/* "$PUBLIC_DIR/" 2>/dev/null
        cp "$REPO_DIR/.htaccess" "$PUBLIC_DIR/" 2>/dev/null
        cp "$REPO_DIR/.env.example" "$PUBLIC_DIR/" 2>/dev/null
        echo -e "${GREEN}✓ Arquivos copiados${NC}"
    fi
else
    # Criar symlink (recomendado)
    ln -sfn "$REPO_DIR" "$PUBLIC_DIR"
    echo -e "${GREEN}✓ Symlink criado: $PUBLIC_DIR -> $REPO_DIR${NC}"
fi

echo ""

# --------------------------------------------
# 3. Configurar .env se não existir
# --------------------------------------------
echo "[3/7] Verificando arquivo .env..."

if [ ! -f "$PUBLIC_DIR/.env" ]; then
    echo -e "${YELLOW}! .env não encontrado. Criando a partir do exemplo...${NC}"
    cp "$PUBLIC_DIR/.env.example" "$PUBLIC_DIR/.env"
    echo -e "${YELLOW}⚠ ATENÇÃO: Edite .env com suas credenciais reais!${NC}"
    echo "   Use: nano $PUBLIC_DIR/.env"
else
    echo -e "${GREEN}✓ .env já existe (protegido)${NC}"
fi

echo ""

# --------------------------------------------
# 4. Criar diretório de logs
# --------------------------------------------
echo "[4/7] Configurando diretório de logs..."

if [ ! -d "$PUBLIC_DIR/logs" ]; then
    mkdir -p "$PUBLIC_DIR/logs"
    echo -e "${GREEN}✓ Diretório logs/ criado${NC}"
else
    echo -e "${GREEN}✓ Diretório logs/ já existe${NC}"
fi

echo ""

# --------------------------------------------
# 5. Configurar permissões
# --------------------------------------------
echo "[5/7] Configurando permissões de segurança..."

chmod 755 "$PUBLIC_DIR/public" 2>/dev/null
chmod 700 "$PUBLIC_DIR/config" 2>/dev/null
chmod 700 "$PUBLIC_DIR/logs" 2>/dev/null
chmod 600 "$PUBLIC_DIR/.env" 2>/dev/null

# Arquivos PHP públicos
if [ -d "$PUBLIC_DIR/public" ]; then
    find "$PUBLIC_DIR/public" -name "*.php" -exec chmod 644 {} \;
fi

# Workers
if [ -d "$PUBLIC_DIR/workers" ]; then
    find "$PUBLIC_DIR/workers" -name "*.php" -exec chmod 644 {} \;
fi

echo -e "${GREEN}✓ Permissões configuradas${NC}"
echo ""

# --------------------------------------------
# 6. Verificar migrations pendentes
# --------------------------------------------
echo "[6/7] Verificando migrations pendentes..."

if [ -d "$PUBLIC_DIR/database/migrations" ]; then
    echo "Migrations disponíveis:"
    ls -1 "$PUBLIC_DIR/database/migrations/" | while read -r migration; do
        echo "  - $migration"
    done
    
    echo ""
    echo -e "${YELLOW}⚠ Execute as migrations manualmente via SSH ou phpMyAdmin${NC}"
    echo "   Exemplo:"
    echo "   mysql -u coinup66_usuario -p -h localhost coinup66_coinup < $PUBLIC_DIR/database/migrations/006_wp3_token_prices_improvements.sql"
else
    echo -e "${YELLOW}! Diretório de migrations não encontrado${NC}"
fi

echo ""

# --------------------------------------------
# 7. Verificar estrutura
# --------------------------------------------
echo "[7/7] Verificando estrutura do projeto..."

DIRS_OK=true

for dir in "public" "config" "database" "workers" "assets"; do
    if [ -d "$PUBLIC_DIR/$dir" ]; then
        echo -e "${GREEN}  ✓ $dir/${NC}"
    else
        echo -e "${RED}  ✗ $dir/ (faltando)${NC}"
        DIRS_OK=false
    fi
done

echo ""

if [ "$DIRS_OK" = true ]; then
    echo -e "${GREEN}╔══════════════════════════════════════════════════════════╗${NC}"
    echo -e "${GREEN}║  ✅ Setup concluído com sucesso!                       ║${NC}"
    echo -e "${GREEN}╚══════════════════════════════════════════════════════════╝${NC}"
else
    echo -e "${RED}╔══════════════════════════════════════════════════════════╗${NC}"
    echo -e "${RED}║  ⚠ Alguns diretórios estão faltando. Verifique acima.  ║${NC}"
    echo -e "${RED}╚══════════════════════════════════════════════════════════╝${NC}"
fi

echo ""

# --------------------------------------------
# Próximos passos
# --------------------------------------------
echo "📋 Próximos passos:"
echo ""
echo "1. Edite o arquivo .env com suas credenciais:"
echo "   nano $PUBLIC_DIR/.env"
echo ""
echo "2. Execute as migrations pendentes no banco de dados"
echo ""
echo "3. Configure os Cron Jobs no cPanel:"
echo "   - Sync Blockchain: */30 * * * * php $PUBLIC_DIR/workers/sync_blockchain.php"
echo "   - Fetch Preços:    */15 * * * * php $PUBLIC_DIR/workers/fetch_prices.php"
echo "   - Fetch Benchmarks: 0 18 * * * php $PUBLIC_DIR/workers/fetch_benchmarks.php"
echo ""
echo "4. Teste o acesso: https://coinup.com.br/main/public/login.php"
echo ""
echo "5. Verifique os logs: tail -f $PUBLIC_DIR/logs/*.log"
echo ""
