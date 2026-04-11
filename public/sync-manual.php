<?php
/**
 * Sync Manual - Interface Web para testes de sincronização
 * Acessível apenas por admin autenticado
 * URL: https://coinup.com.br/main/public/sync-manual.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/auth.php';
require_once dirname(__DIR__) . '/config/middleware.php';

require_once dirname(__DIR__) . '/src/Utils/WeiConverter.php';
require_once dirname(__DIR__) . '/src/Blockchain/NetworkConfig.php';
require_once dirname(__DIR__) . '/src/Blockchain/AlchemyClient.php';
require_once dirname(__DIR__) . '/src/Services/TransactionParser.php';
require_once dirname(__DIR__) . '/src/Services/SyncService.php';

// Autenticação via Middleware
Middleware::requireAuth();
Middleware::requireAdmin();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['user_role'] !== 'admin') {
    header('Location: /main/public/login.php');
    exit;
}

$result = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $walletId = (int)($_POST['wallet_id'] ?? 0);
    
    if (!$walletId) {
        $error = 'Selecione uma carteira para sincronizar.';
    } else {
        try {
            $db = Database::getInstance()->getConnection();
            
            // Buscar wallet
            $stmt = $db->prepare("SELECT * FROM wallets WHERE id = ?");
            $stmt->execute([$walletId]);
            $wallet = $stmt->fetch();
            
            if (!$wallet) {
                $error = 'Carteira não encontrada.';
            } else {
                // Carregar chaves
                $alchemy_keys = [
                    'ethereum' => getenv('ALCHEMY_ETHEREUM_KEY'),
                    'bnb' => getenv('ALCHEMY_BNB_KEY'),
                    'arbitrum' => getenv('ALCHEMY_ARBITRUM_KEY'),
                    'base' => getenv('ALCHEMY_BASE_KEY'),
                    'polygon' => getenv('ALCHEMY_POLYGON_KEY'),
                ];
                
                $syncService = new SyncService($db, $alchemy_keys);
                $result = $syncService->syncWallet($wallet);
            }
        } catch (Exception $e) {
            $error = 'Erro: ' . $e->getMessage();
        }
    }
}

// Buscar todas as wallets
$db = Database::getInstance()->getConnection();
$stmt = $db->query("
    SELECT w.*, u.name as user_name
    FROM wallets w
    JOIN users u ON w.user_id = u.id
    WHERE u.status != 'deleted'
    ORDER BY w.created_at DESC
");
$wallets = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sync Manual - CoinUp</title>
    <link rel="stylesheet" href="/main/assets/css/style.css">
    <style>
        .sync-container { max-width: 800px; margin: 0 auto; }
        .result-box {
            background: rgba(74, 222, 128, 0.1);
            border: 1px solid rgba(74, 222, 128, 0.3);
            border-radius: 10px;
            padding: 20px;
            margin-top: 20px;
        }
        .error-box {
            background: rgba(248, 113, 113, 0.1);
            border: 1px solid rgba(248, 113, 113, 0.3);
            border-radius: 10px;
            padding: 20px;
            margin-top: 20px;
            color: #f87171;
        }
        .stat-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid rgba(255,255,255,0.05);
        }
    </style>
</head>
<body>
    <div class="dashboard-container" style="display: flex; min-height: 100vh;">
        <aside class="sidebar">
            <div class="logo"><h1>🪙 CoinUp</h1></div>
            <ul class="nav-menu">
                <li><a href="/main/public/admin.php"><span>👥 Clientes</span></a></li>
                <li><a href="/main/public/admin-wallets.php"><span>🔗 Carteiras</span></a></li>
                <li><a href="/main/public/admin-sync.php"><span>🔄 Sincronização</span></a></li>
                <li><a href="/main/public/sync-manual.php" class="active"><span>🔧 Sync Manual</span></a></li>
                <li><a href="/main/public/admin-logs.php"><span>📋 Logs</span></a></li>
            </ul>
            <div class="user-info">
                <p><strong>Admin</strong></p>
                <a href="/main/public/logout.php" style="color: #fca5a5;">Sair</a>
            </div>
        </aside>

        <main class="main-content">
            <div class="header">
                <div>
                    <h2>🔧 Sincronização Manual</h2>
                    <p>Execute sync para uma carteira específica</p>
                </div>
                <a href="/main/public/admin-sync.php" class="btn btn-secondary">← Voltar</a>
            </div>

            <div class="sync-container">
                <?php if ($error): ?>
                    <div class="error-box">❌ <?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <?php if ($result): ?>
                    <div class="result-box">
                        <h3 style="color: #4ade80; margin-bottom: 15px;">✅ Sincronização Concluída</h3>
                        <div class="stat-row">
                            <span>Wallet ID:</span>
                            <strong>#<?= $result['wallet_id'] ?></strong>
                        </div>
                        <div class="stat-row">
                            <span>Rede:</span>
                            <strong><?= ucfirst($result['network']) ?></strong>
                        </div>
                        <div class="stat-row">
                            <span>Transações encontradas:</span>
                            <strong><?= $result['transactions_found'] ?></strong>
                        </div>
                        <div class="stat-row">
                            <span>Blocos processados:</span>
                            <strong><?= $result['blocks_processed'] ?></strong>
                        </div>
                        <div class="stat-row">
                            <span>Status:</span>
                            <strong><?= ucfirst($result['status']) ?></strong>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="card">
                    <h3 style="color: #fff; margin-bottom: 20px;">Selecionar Carteira</h3>
                    
                    <?php if (count($wallets) > 0): ?>
                        <form method="POST">
                            <div class="form-group">
                                <label class="form-label">Carteira</label>
                                <select name="wallet_id" class="form-select" required>
                                    <option value="">Selecione uma carteira...</option>
                                    <?php foreach ($wallets as $w): ?>
                                        <option value="<?= $w['id'] ?>">
                                            #<?= $w['id'] ?> - <?= htmlspecialchars($w['user_name']) ?> - 
                                            <?= ucfirst($w['network']) ?> - 
                                            <?= substr($w['address'], 0, 10) ?>...<?= substr($w['address'], -8) ?>
                                            <?= $w['label'] ? '(' . htmlspecialchars($w['label']) . ')' : '' ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-primary" style="width: 100%;">
                                🔄 Executar Sincronização
                            </button>
                        </form>
                    <?php else: ?>
                        <div class="empty-state">
                            <p>Nenhuma carteira cadastrada</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
