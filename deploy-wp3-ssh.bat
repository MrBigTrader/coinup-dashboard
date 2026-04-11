@echo off
REM ============================================
REM COINUP DASHBOARD - Deploy WP3 (v4.0)
REM Execução direta via SSH sem script temporário
REM ============================================

chcp 65001 >nul
echo.
echo ╔══════════════════════════════════════════════════════════╗
echo ║         COINUP WP3 - DEPLOY DIRETO                     ║
echo ══════════════════════════════════════════════════════════╝
echo.

set "SSH_KEY=%USERPROFILE%\.ssh\id_rsa"

if not exist "%SSH_KEY%" (
    echo [ERRO] Chave SSH não encontrada: %SSH_KEY%
    pause
    exit /b 1
)

echo [PASSO 1/3] Upload do worker...
scp -i "%SSH_KEY%" -P 22 workers\fetch_prices.php coinup66@coinup.com.br:/home2/coinup66/public_html/main/workers/
if errorlevel 1 ( echo [ERRO] Falha! & pause & exit /b 1 )
echo [OK] Worker enviado!

echo.
echo [PASSO 2/3] Upload da migration...
scp -i "%SSH_KEY%" -P 22 database\migrations\006_wp3_token_prices_improvements.sql coinup66@coinup.com.br:/home2/coinup66/public_html/main/database/migrations/
if errorlevel 1 ( echo [ERRO] Falha! & pause & exit /b 1 )
echo [OK] Migration enviada!

echo.
echo [PASSO 3/3] Executando no servidor...
echo.
echo ┌──────────────────────────────────────────────────────────┐
echo │ Execute os comandos abaixo manualmente no SSH:          │
echo │                                                          │
echo │ ssh -i "%SSH_KEY%" -p 22 coinup66@coinup.com.br         │
echo │                                                          │
echo │ cd /home2/coinup66/public_html/main                     │
echo │ mysql -u coinup66_usuario -p coinup66_coinup ^<          │
echo │   database/migrations/006_wp3_token_prices_improvements.sql│
echo │                                                          │
echo │ php workers/fetch_prices.php --verbose                  │
echo └──────────────────────────────────────────────────────────┘
echo.
echo [INFO] Senha do banco: P#J(auPX.*1C
echo.

pause
