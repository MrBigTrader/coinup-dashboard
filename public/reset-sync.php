<?php
/**
 * Reset Sync - Resetar sincronização de uma wallet
 * Revisão: 2026-04-06-FinalFix
 * 
 * Este script limpa o sync_state e transactions_cache para re-sync completo.
 * 
 * USO: Via browser (apenas admin)
 * URL: https://coinup.com.br/main/public/reset-sync.php?wallet_id=5
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/auth.php';

if (session_status() === PHP_SESSION_NONE) {
    session_name('COINUPSESS');
    session_start();
}

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['user_role'] !== 'admin') {
    die('Acesso negado. Apenas administradores.');
}

$walletId = isset($_GET['wallet_id']) ? (int)$_GET['wallet_id'] : 0;
$resetDone = false;
$deletedTxs = 0;
$deletedSync = 0;

if ($walletId > 0 && isset($_GET['confirm']) && $_GET['confirm'] === 'yes') {
    try {
        $db = Database::getInstance()->getConnection();
        
        // Buscar wallet para confirmar
        $stmt = $db->prepare("SELECT w.*, u.name as user_name FROM wallets w JOIN users u ON w.user_id = u.id WHERE w.id = ?");
        $stmt->execute([$walletId]);
        $wallet = $stmt->fetch();
        
        if ($wallet) {
            // Deletar transações da wallet
            $stmt = $db->prepare("DELETE FROM transactions_cache WHERE wallet_id = ?");
            $stmt->execute([$walletId]);
            $deletedTxs = $stmt->rowCount();
            
            // Deletar sync_state da wallet
            $stmt = $db->prepare("DELETE FROM sync_state WHERE wallet_id = ?");
            $stmt->execute([$walletId]);
            $deletedSync = $stmt->rowCount();
            
            // Resetar contadores de erro
            $stmt = $db->prepare("UPDATE wallets SET sync_error_count = 0, last_sync_attempt = NULL WHERE id = ?");
            $stmt->execute([$walletId]);
            
            $resetDone = true;
        }
    } catch (Exception $e) {
        $error = "ERRO: " . $e->getMessage();
    }
}

// Buscar todas as wallets para listar
$db = Database::getInstance()->getConnection();
$stmt = $db->query("
    SELECT w.*, u.name as user_name,
           (SELECT last_block_synced FROM sync_state WHERE wallet_id = w.id AND network = w.network) as last_block,
           (SELECT COUNT(*) FROM transactions_cache WHERE wallet_id = w.id) as tx_count
    FROM wallets w
    JOIN users u ON w.user_id = u.id
    ORDER BY w.created_at DESC
");
$wallets = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Sync - CoinUp</title>
    <link rel="stylesheet" href="/main/assets/css/style.css">
    <style>
        body { font-family: 'Segoe UI', system-ui, sans-serif; background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%); color: #eee; padding: 20px; }
        .container { max-width: 900px; margin: 0 auto; }
        .warning { background: rgba(255, 193, 7, 0.2); border: 1px solid #ffc107; padding: 15px; border-radius: 8px; margin: 20px 0; }
        .success { background: rgba(76, 175, 80, 0.2); border: 1px solid #4caf50; padding: 15px; border-radius: 8px; margin: 20px 0; }
        .error { background: rgba(244, 67, 54, 0.2); border: 1px solid #f44336; padding: 15px; border-radius: 8px; margin: 20px 0; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 10px; border: 1px solid #333; text-align: left; }
        th { background: #16213e; }
        tr:hover { background: #16213e; }
        .btn { display: inline-block; padding: 8px 16px; border-radius: 6px; text-decoration: none; margin: 5px; }
        .btn-danger { background: #f44336; color: white; }
        .btn-success { background: #4caf50; color: white; }
        .btn-secondary { background: #555; color: white; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔄 Reset de Sincronização</h1>
        <p>Limpa o histórico de sync para re-sincronização completa desde o primeiro bloco</p>
        
        <?php if (isset($error)): ?>
            <div class="error">❌ <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <?php if ($resetDone): ?>
            <div class="success">
                <h3>✅ Sync Resetado com Sucesso!</h3>
                <p><strong>Wallet #<?= $walletId ?></strong> (<?= htmlspecialchars($wallet['user_name']) ?>)</p>
                <p>Rede: <?= ucfirst($wallet['network']) ?></p>
                <p>Endereço: <code><?= $wallet['address'] ?></code></p>
                <hr style="border-color: rgba(255,255,255,0.1); margin: 15px 0;">
                <p>📊 Transações removidas: <strong><?= $deletedTxs ?></strong></p>
                <p>📊 Sync state removido: <strong><?= $deletedSync ?></strong></p>
                <hr style="border-color: rgba(255,255,255,0.1); margin: 15px 0;">
                <p>⏳ Agora execute o <a href="/main/public/sync-manual.php" class="btn btn-success">Sync Manual</a> para iniciar a sincronização completa.</p>
                <p style="margin-top: 10px; font-size: 12px; color: #94a3b8;">
                    💡 O sistema usará a otimização de primeiro bloco (encontrado em ~<?= $wallet['network'] === 'bnb' ? '57M' : 'calcular' ?>)
                </p>
            </div>
        <?php endif; ?>
        
        <?php if (!$resetDone && $walletId > 0): ?>
            <?php
            $stmt = $db->prepare("SELECT w.*, u.name as user_name FROM wallets w JOIN users u ON w.user_id = u.id WHERE w.id = ?");
            $stmt->execute([$walletId]);
            $wallet = $stmt->fetch();
            ?>
            <div class="warning">
                <h3>⚠️ Confirmação Necessária</h3>
                <p>Tem certeza que deseja resetar o sync da seguinte wallet?</p>
                <table>
                    <tr><th>Wallet ID</th><td>#<?= $walletId ?></td></tr>
                    <tr><th>Usuário</th><td><?= htmlspecialchars($wallet['user_name']) ?></td></tr>
                    <tr><th>Rede</th><td><?= ucfirst($wallet['network']) ?></td></tr>
                    <tr><th>Endereço</th><td><code><?= $wallet['address'] ?></code></td></tr>
                </table>
                <p><strong>Esta ação irá:</strong></p>
                <ul>
                    <li>❌ Deletar todas as transações sincronizadas desta wallet</li>
                    <li>❌ Resetar o último bloco sincronizado (permitindo sync desde o bloco 0)</li>
                    <li>🔄 Permitir re-sincronização completa com otimização de primeiro bloco</li>
                </ul>
                <p style="margin-top: 20px;">
                    <a href="?wallet_id=<?= $walletId ?>&confirm=yes" class="btn btn-danger">✅ Sim, Resetar Sync</a>
                    <a href="/main/public/sync-manual.php" class="btn btn-secondary">Cancelar</a>
                </p>
            </div>
        <?php endif; ?>
        
        <h2>Carteiras Disponíveis para Reset</h2>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Usuário</th>
                    <th>Rede</th>
                    <th>Endereço</th>
                    <th>Último Bloco</th>
                    <th>Transações</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($wallets as $w): ?>
                    <tr>
                        <td>#<?= $w['id'] ?></td>
                        <td><?= htmlspecialchars($w['user_name']) ?></td>
                        <td><?= ucfirst($w['network']) ?></td>
                        <td><code><?= substr($w['address'], 0, 10) ?>...</code></td>
                        <td><?= $w['last_block'] ? number_format($w['last_block']) : '0' ?></td>
                        <td><?= $w['tx_count'] ?></td>
                        <td>
                            <a href="?wallet_id=<?= $w['id'] ?>" class="btn btn-danger" style="font-size: 12px;">Resetar</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #333;">
            <a href="/main/public/sync-manual.php" class="btn btn-secondary">← Voltar para Sync Manual</a>
            <a href="/main/public/admin-sync.php" class="btn btn-secondary">Voltar para Sincronização</a>
        </div>
        
        <div class="warning" style="margin-top: 20px;">
            <p>⚠️ <strong>Após usar este script, REMOVA-O do servidor por segurança!</strong></p>
        </div>
    </div>
</body>
</html>
