<?php
/**
 * Worker: Sincronização Blockchain (WP2 - Debug Profundo)
 * Revisão: 2026-04-07-007 (Debug Profundo + Fallback de Erro)
 * 
 * Este script foi desenhado para nunca falhar em silêncio.
 * Qualquer erro, fatal ou não, será gravado no log.
 */

// Iniciar buffer de saída para capturar tudo
ob_start();

// Configurar tratamento de erros
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

// Função para escrever no log manualmente (fallback)
function write_to_log($message) {
    $log_file = dirname(__DIR__) . '/logs/cron_debug.log';
    $log_dir = dirname($log_file);
    
    // Garantir que a pasta existe
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
    
    file_put_contents($log_file, date('Y-m-d H:i:s') . " - " . $message . "\n", FILE_APPEND);
}

// Registrar shutdown function para capturar erros fatais
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && ($error['type'] === E_ERROR || $error['type'] === E_PARSE)) {
        write_to_log("[FATAL ERROR] " . $error['message'] . " in " . $error['file'] . " on line " . $error['line']);
        // Tentar imprimir também para o stdout
        echo "[FATAL ERROR] " . $error['message'] . "\n";
    }
    
    // Escrever o buffer de saída no log secundário
    $output = ob_get_clean();
    if (!empty($output)) {
        write_to_log("[OUTPUT] " . $output);
    }
});

write_to_log("[START] Script iniciado.");

try {
    write_to_log("[INFO] Tentando carregar classes...");
    
    // Caminho base
    $base_path = dirname(__DIR__);
    
    // Verificar se os arquivos existem antes de incluir
    $files_to_check = [
        'config/database.php',
        'src/Utils/WeiConverter.php',
        'src/Blockchain/NetworkConfig.php',
        'src/Blockchain/AlchemyClient.php',
        'src/Services/TransactionParser.php',
        'src/Services/SyncService.php'
    ];
    
    foreach ($files_to_check as $file) {
        $full_path = $base_path . '/' . $file;
        if (file_exists($full_path)) {
            write_to_log("[OK] Arquivo encontrado: $file");
        } else {
            write_to_log("[ERRO] Arquivo NÃO encontrado: $full_path");
            die("[ERRO] Arquivo crítico faltando: $file");
        }
    }
    
    // Carregar classes
    require_once $base_path . '/config/database.php';
    require_once $base_path . '/src/Utils/WeiConverter.php';
    require_once $base_path . '/src/Blockchain/NetworkConfig.php';
    require_once $base_path . '/src/Blockchain/AlchemyClient.php';
    require_once $base_path . '/src/Services/TransactionParser.php';
    require_once $base_path . '/src/Services/SyncService.php';
    
    write_to_log("[OK] Todas as classes carregadas.");
    
    // Conexão DB
    write_to_log("[DB] Tentando conectar...");
    $db = Database::getInstance()->getConnection();
    write_to_log("[DB] Conexão OK.");
    
    // Carregar chaves
    $env_file = $base_path . '/.env';
    if (!file_exists($env_file)) {
        die("[ERRO] Arquivo .env não encontrado em: $env_file");
    }
    
    $env_content = file_get_contents($env_file);
    $alchemy_keys = [];
    foreach (['ETHEREUM', 'BNB', 'ARBITRUM', 'BASE', 'POLYGON'] as $net) {
        if (preg_match("/^ALCHEMY_{$net}_KEY=(.+)$/m", $env_content, $matches)) {
            $alchemy_keys[strtolower($net)] = trim($matches[1]);
            write_to_log("[KEY] Chave carregada para: $net");
        }
    }
    
    if (empty($alchemy_keys)) {
        die("[ERRO] Nenhuma chave API encontrada no .env");
    }
    
    write_to_log("[SYNC] Iniciando SyncService...");
    
    // Instanciar serviço
    $syncService = new SyncService($db, $alchemy_keys, 200000);
    $result = $syncService->syncAllWallets();
    
    write_to_log("[RESULT] Wallets: " . $result['wallets_processed'] . ", TXs: " . $result['transactions_found']);
    
    echo "Sucesso!";
    
} catch (Exception $e) {
    $msg = "[EXCEPTION] " . $e->getMessage() . "\nTrace: " . $e->getTraceAsString();
    write_to_log($msg);
    echo $msg;
}
?>
