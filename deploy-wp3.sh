#!/bin/bash
# ============================================
# COINUP DASHBOARD - Deploy WP3 (Worker CoinGecko)
# ============================================
# Uso: bash deploy-wp3.sh
# ============================================

echo "╔══════════════════════════════════════════════════════════╗"
echo "║         COINUP WP3 DEPLOY SCRIPT                      ║"
echo "╚══════════════════════════════════════════════════════════╝"
echo ""

# Configurações
PROJECT_DIR="/home2/coinup66/public_html/main"
LOCAL_DIR="$(cd "$(dirname "$0")" && pwd)"

echo "[1/5] Conectando ao servidor..."
ssh coinup66@coinup.com.br << 'ENDSSH'

echo "✓ Conectado com sucesso!"
echo ""

# Navegar para o projeto
cd /home2/coinup66/public_html/main
echo "[2/5] Navegando para: $(pwd)"

echo ""
echo "[3/5] Executando migration 006..."
mysql -u coinup66_usuario -p'SUA_SENHA_AQUI' -h localhost coinup66_coinup < database/migrations/006_wp3_token_prices_improvements.sql
echo "✓ Migration aplicada!"

echo ""
echo "[4/5] Verificando estrutura da tabela..."
mysql -u coinup66_usuario -p'SUA_SENHA_AQUI' -h localhost coinup66_coinup -e "DESCRIBE token_prices;"

echo ""
echo "[5/5] Testando worker..."
php workers/fetch_prices.php --verbose

echo ""
echo "╔══════════════════════════════════════════════════════════╗"
echo "║                    DEPLOY CONCLUÍDO                    ║"
echo "╚══════════════════════════════════════════════════════════╝"

ENDSSH

echo ""
echo "Deploy concluído!"
echo ""
echo "Próximos passos:"
echo "  1. Verifique se os preços foram atualizados"
echo "  2. Configure o cron job no cPanel:"
echo "     */15 * * * * php /home2/coinup66/public_html/main/workers/fetch_prices.php"
echo ""
