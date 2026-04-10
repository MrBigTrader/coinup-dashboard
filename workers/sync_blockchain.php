<?php
/**
 * Worker: Sincronização Blockchain (WP2 - Final Stable)
 * Revisão: 2026-04-09-Final-Stable
 * 
 * Script principal de sincronização.
 * Usa o método de log comprovado (escrita direta em workers/sync.log).
 */

// Configuração de Log (Caminho absoluto para garantir escrita)
$log_file = dirname(__FILE__) . '/sync.log';

function w_log($msg) {
    global $log_file;
    $txt = date('H:i:s') . " - $msg\n";
    // Tenta escrever no arquivo; se falhar, tenta o error_log do sistema
    if (!@file_put_contents($log_file, $txt, FILE_APPEND)) {
        error_log("FALHA NO LOG: $txt");
    }
    echo $txt;
}

w_log("==================================================");
w_log("[START] Worker de Sincronização Iniciado.");
w_log("==================================================");

try {
    $base_path = dirname(__DIR__);
    w_log("[PATH] Base definida: $base_path");

    // 1. Carregar Database
    w_log("[LOAD 1/6] Carregando config/database.php...");
    require_once $base_path . '/config/database.php';
    w_log("[OK] Database OK.");

    // 2. Carregar Utils
    w_log("[LOAD 2/6] Carregando Utils/WeiConverter.php...");
    require_once $base_path . '/src/Utils/WeiConverter.php';
    w_log("[OK] WeiConverter OK.");

    // 3. Carregar Blockchain Config
    w_log("[LOAD 3/6] Carregando Blockchain/NetworkConfig.php...");
    require_once $base_path . '/src/Blockchain/NetworkConfig.php';
    w_log("[OK] NetworkConfig OK.");

    // 4. Carregar Alchemy Client
    w_log("[LOAD 4/6] Carregando Blockchain/AlchemyClient.php...");
    require_once $base_path . '/src/Blockchain/AlchemyClient.php';
    w_log("[OK] AlchemyClient OK.");

    // 5. Carregar Transaction Parser
    w_log("[LOAD 5/6] Carregando Services/TransactionParser.php...");
    require_once $base_path . '/src/Services/TransactionParser.php';
    w_log("[OK] TransactionParser OK.");

    // 6. Carregar Sync Service
    w_log("[LOAD 6/6] Carregando Services/SyncService.php...");
    require_once $base_path . '/src/Services/SyncService.php';
    w_log("[OK] SyncService OK.");

    // Conexão com DB
    w_log("[DB] Conectando ao banco de dados...");
    $db = Database::getInstance()->getConnection();
    w_log("[OK] Conexão estabelecida.");

    // Carregar Chaves do .env
    w_log("[ENV] Lendo chaves do .env...");
    $env_file = $base_path . '/.env';
    if (!file_exists($env_file)) {
        throw new Exception("Arquivo .env não encontrado em: $env_file");
    }
    
    $env_content = file_get_contents($env_file);
    $alchemy_keys = [];
    foreach (['ETHEREUM', 'BNB', 'ARBITRUM', 'BASE', 'POLYGON'] as $net) {
        if (preg_match("/^ALCHEMY_{$net}_KEY=(.+)$/m", $env_content, $matches)) {
            $alchemy_keys[strtolower($net)] = trim($matches[1]);
        }
    }
    w_log("[OK] " . count($alchemy_keys) . " chaves carregadas.");

    // Executar Sincronização
    w_log("[EXEC] Instanciando SyncService e iniciando sync...");
    $syncService = new SyncService($db, $alchemy_keys, 200000); // 200k blocos padrão
    $result = $syncService->syncAllWallets();

    w_log("[RESULT] ===========================================");
    w_log("[RESULT] Wallets processadas: " . $result['wallets_processed']);
    w_log("[RESULT] Transações encontradas: " . $result['transactions_found']);
    w_log("[RESULT] Erros: " . $result['errors']);
    w_log("[RESULT] ===========================================");

} catch (Exception $e) {
    w_log("[FATAL ERROR] " . $e->getMessage());
    w_log("[TRACE] " . $e->getTraceAsString());
}

w_log("[END] Worker finalizado.");
w_log("==================================================");
?>