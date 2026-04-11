@echo off
REM ============================================
REM COINUP - Configurar Chave SSH
REM ============================================
REM Copia sua chave pública para o servidor
REM para evitar pedir senha interativamente.
REM ============================================

echo.
echo ╔══════════════════════════════════════════════════════════╗
echo ║         COINUP - CONFIGURAR CHAVE SSH                  ║
echo ══════════════════════════════════════════════════════════╝
echo.

set "REMOTE_USER=coinup66"
set "REMOTE_HOST=coinup.com.br"

echo [INFO] Copiando chave pública para o servidor...
echo.

REM Criar script remoto para adicionar chave
set "TEMP_SCRIPT=%TEMP%\setup_ssh_key.sh"

(
echo mkdir -p ~/.ssh
echo chmod 700 ~/.ssh
echo cat ^>^> ~/.ssh/authorized_keys ^<^< 'KEY_EOF'
type "%~dp0id_rsa.pub"
echo KEY_EOF
echo chmod 600 ~/.ssh/authorized_keys
echo echo "[OK] Chave SSH configurada com sucesso!"
) > "%TEMP_SCRIPT%"

ssh %REMOTE_USER%@%REMOTE_HOST% "bash -s" < "%TEMP_SCRIPT%"

del "%TEMP_SCRIPT%"

echo.
if errorlevel 1 (
    echo [ERRO] Falha ao configurar chave SSH!
    echo [INFO] Tente manualmente:
    echo   type id_rsa.pub | ssh coinup66@coinup.com.br "mkdir -p ~/.ssh && cat >> ~/.ssh/authorized_keys"
    pause
    exit /b 1
)

echo ╔══════════════════════════════════════════════════════════╗
echo ║              CHAVE SSH CONFIGURADA!                    ║
echo ╚══════════════════════════════════════════════════════════╝
echo.
echo Agora você pode executar o deploy sem digitar senha!
echo.
echo Teste a conexão:
echo   ssh coinup66@coinup.com.br
echo.
pause
