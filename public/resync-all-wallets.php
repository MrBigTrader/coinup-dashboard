<?php
/**
 * Resync Inteligente - Aplica otimização de primeiro bloco em TODAS as carteiras
 * Revisão: 2026-04-07-LocalFirst
 * 
 * USO: Via browser (apenas admin) ou CLI
 * URL: https://coinup.com.br/main/public/resync-all-wallets.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
set_time_limit(600); // 10 minutos

require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/auth.php';

if (session_status() === PHP_SESSION_NONE) {
    session_name('COINUPSESS');
    session_start();
}

$is_cli = php_sapi_name() === 'cli';
if (!$is_cli && (!isset($_SESSION['logged_in']) || $_SESSION['user_role'] !== 'admin')) {
    die('Acesso negado.');
}

require_once dirname(__DIR__) . '/src/Blockchain/NetworkConfig.php';
require_once dirname(__DIR__) . '/src/Blockchain/AlchemyClient.php';

$db = Database::getInstance()->getConnection();

// Carregar chaves Alchemy
$envFile = dirname(__DIR__) . '/.env';
$envContent = file_get_contents($envFile);
$alchemyKeys = [];
foreach (['ETHEREUM', 'BNB', 'ARBITRUM', 'BASE', 'POLYGON'] as $net) {
    if (preg_match("/^ALCHEMY_{$net}_KEY=(.+)$/m", $envContent, $matches)) {
        $alchemyKeys[strtolower($net)] = trim($matches[1]);
    }
}

// Buscar todas as wallets ativas
$stmt = $db->query("SELECT * FROM wallets WHERE is_active = 1");
$wallets = $stmt->fetchAll();

echo "<h1>🔄 Resync Inteligente - Otimizando Todas as Carteiras</h1>";
echo "<p>Este script encontra o primeiro bloco com atividade de cada carteira e reseta o ponto de partida.</p>";
echo "<hr>";

$totalSaved = 0;
$processed = 0;

foreach ($wallets as $wallet) {
    $walletId = $wallet['id'];
    $network = $wallet['network'];
    $address = $wallet['address'];
    
    echo "<h3>📍 Wallet #{$walletId} ({$network})</h3>";
    echo "<p>Endereço: <code>{$address}</code></p>";
    
    // Verificar estado atual
    $stmt = $db->prepare("SELECT last_block_synced FROM sync_state WHERE wallet_id = ? AND network = ?");
    $stmt->execute([$walletId, $network]);
    $currentState = $stmt->fetch();
    $currentBlock = $currentState ? (int)$currentState['last_block_synced'] : 0;
    
    echo "<p>Bloco atual no DB: <strong>" . number_format($currentBlock) . "</strong></p>";
    
    // Se já está otimizado (bloco > 1000000), pular
    if ($currentBlock > 1000000) {
        echo "<p style='color: #4ade80;'>✅ Já otimizada (bloco > 1M). Pulando...</p><hr>";
        continue;
    }
    
    // Encontrar primeiro bloco com atividade
    try {
        if (!isset($alchemyKeys[$network])) {
            echo "<p style='color: #f87171;'>❌ Sem chave API para {$network}. Pulando...</p><hr>";
            continue;
        }
        
        $alchemy = new AlchemyClient($network, $alchemyKeys[$network]);
        
        echo "<p>🔍 Buscando primeiro bloco com atividade...</p>";
        $firstBlock = $alchemy->findFirstActivityBlock($address);
        
        if ($firstBlock > 0) {
            echo "<p>✅ Primeiro bloco encontrado: <strong>" . number_format($firstBlock) . "</strong></p>";
            
            // Calcular economia
            $savedBlocks = $firstBlock - $currentBlock;
            $totalSaved += $savedBlocks;
            $processed++;
            
            // Resetar sync_state para começar do primeiro bloco
            $stmt = $db->prepare("
                INSERT INTO sync_state (wallet_id, network, last_block_synced, last_sync_at, sync_status)
                VALUES (?, ?, ?, NOW(), 'idle')
                ON DUPLICATE KEY UPDATE 
                    last_block_synced = VALUES(last_block_synced),
                    last_sync_at = NOW(),
                    sync_status = 'idle'
            ");
            $stmt->execute([$walletId, $network, $firstBlock - 10]); // -10 para margem
            
            // Limpar transações antigas se houver (para evitar duplicatas)
            $stmt = $db->prepare("DELETE FROM transactions_cache WHERE wallet_id = ? AND block_number < ?", 
            [$walletId, $firstBlock]);
            $deleted = $stmt->rowCount();
            
            echo "<p style='color: #4ade80;'>✅ Wallet #{$walletId} otimizada! Economia: " . number_format($savedBlocks) . " blocos.</p>";
            if ($deleted > 0) echo "<p>🗑️ Transações antigas removidas: {$deleted}</p>";
            
        } else {
            echo "<p style='color: #fbbf24;'>⚠️ Nenhuma atividade encontrada. Mantendo bloco 0.</p>";
        }
        
    } catch (Exception $e) {
        echo "<p style='color: #f87171;'>❌ Erro: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
    
    echo "<hr>";
    
    // Delay para evitar rate limit
    usleep(500000); // 0.5s entre wallets
}

echo "<h2>📊 Resumo Final</h2>";
echo "<p><strong>Carteiras processadas:</strong> {$processed}</p>";
echo "<p><strong>Total de blocos economizados:</strong> " . number_format($totalSaved) . "</p>";
echo "<p><strong>Tempo estimado economizado:</strong> " . round($totalSaved / 50000 * 0.5 / 60, 1) . " horas</p>";
echo "<hr>";
echo "<p>⚠️ <strong>IMPORTANTE:</strong> Após executar, delete este arquivo do servidor por segurança.</p>";
echo "<p><a href='/main/public/sync-monitor.php' style='color: #8b5cf6;'>← Voltar para o Monitor</a></p>";
