<?php
/**
 * Minhas Carteiras - CoinUp Dashboard
 * Lista e gerencia as carteiras EVM do cliente
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/auth.php';
require_once dirname(__DIR__) . '/config/middleware.php';

Middleware::requireAuth();

$auth = Auth::getInstance();
$user = $auth->getCurrentUser();

if (!$auth->isClient()) {
    header('Location: /main/public/admin.php');
    exit;
}

$wallets = [];
$success = $_GET['success'] ?? '';
$error = $_GET['error'] ?? '';

try {
    $db = Database::getInstance()->getConnection();
    $user_id = $auth->getCurrentUserId();

    // Buscar carteiras do usuário
    $stmt = $db->prepare("
        SELECT
            w.id,
            w.network,
            w.address,
            w.label,
            w.is_active,
            w.created_at,
            s.last_block_synced,
            s.last_sync_at,
            s.sync_status,
            COUNT(DISTINCT t.id) as transaction_count
        FROM wallets w
        LEFT JOIN sync_state s ON w.id = s.wallet_id
        LEFT JOIN transactions_cache t ON w.id = t.wallet_id
        WHERE w.user_id = ?
        GROUP BY w.id
        ORDER BY w.created_at DESC
    ");
    $stmt->execute([$user_id]);
    $wallets = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Erro ao buscar carteiras: " . $e->getMessage());
}

$network_labels = [
    'ethereum' => 'Ethereum',
    'bnb' => 'BNB Chain',
    'arbitrum' => 'Arbitrum',
    'base' => 'Base',
    'polygon' => 'Polygon'
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Minhas Carteiras - CoinUp</title>
    <link rel="stylesheet" href="/main/assets/css/style.css">
    <style>
        .address {
            font-family: 'Courier New', monospace;
            font-size: 0.9rem;
            color: #a855f7;
        }

        .wallet-card {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 15px;
            transition: all 0.3s ease;
        }

        .wallet-card:hover {
            border-color: rgba(168, 85, 247, 0.3);
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.2);
        }

        .wallet-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .wallet-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .wallet-icon {
            width: 50px;
            height: 50px;
            background: var(--primary-gradient);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }

        .wallet-details h3 {
            color: #fff;
            margin-bottom: 4px;
        }

        .wallet-details p {
            color: #94a3b8;
            font-size: 0.85rem;
        }

        .wallet-stats {
            display: flex;
            gap: 20px;
            padding-top: 15px;
            border-top: 1px solid rgba(255, 255, 255, 0.05);
        }

        .wallet-stat {
            flex: 1;
        }

        .wallet-stat-label {
            color: #64748b;
            font-size: 0.75rem;
            text-transform: uppercase;
        }

        .wallet-stat-value {
            color: #e2e8f0;
            font-size: 0.9rem;
            margin-top: 4px;
        }

        .wallet-actions {
            display: flex;
            gap: 10px;
        }

        .btn-icon {
            padding: 8px 12px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            color: #94a3b8;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
        }

        .btn-icon:hover {
            background: rgba(255, 255, 255, 0.1);
            color: #fff;
        }

        .btn-icon.danger:hover {
            background: rgba(239, 68, 68, 0.2);
            border-color: rgba(239, 68, 68, 0.3);
            color: #fca5a5;
        }

        .alert {
            padding: 16px;
            border-radius: 10px;
            margin-bottom: 20px;
        }

        .alert-success {
            background: rgba(74, 222, 128, 0.1);
            border: 1px solid rgba(74, 222, 128, 0.3);
            color: #4ade80;
        }

        .alert-error {
            background: rgba(248, 113, 113, 0.1);
            border: 1px solid rgba(248, 113, 113, 0.3);
            color: #f87171;
        }
    </style>
</head>
<body>
    <div class="dashboard-container" style="display: flex; min-height: 100vh;">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="logo">
                <h1>🪙 CoinUp</h1>
            </div>

            <ul class="nav-menu">
                <li><a href="/main/public/dashboard.php"><span>📊 Overview</span></a></li>
                <li><a href="/main/public/my-wallets.php" class="active"><span>🔗 Minhas Carteiras</span></a></li>
                <li><a href="/main/public/assets.php"><span>💼 Assets</span></a></li>
                <li><a href="/main/public/transactions.php"><span>📝 Transactions</span></a></li>
                <li><a href="/main/public/market.php"><span>📈 Market</span></a></li>
            </ul>

            <div class="user-info">
                <p><strong><?= htmlspecialchars($user['name']) ?></strong></p>
                <small><?= htmlspecialchars($user['email']) ?></small>
                <a href="/main/public/logout.php" style="margin-top: 10px; padding: 8px 16px; background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.3); color: #fca5a5; border-radius: 8px; cursor: pointer; width: 100%; text-decoration: none; display: block; text-align: center;">Sair</a>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <div class="header">
                <div>
                    <h2>Minhas Carteiras</h2>
                    <p>Gerencie suas carteiras EVM</p>
                </div>
                <a href="/main/public/add-wallet.php" class="btn btn-primary">+ Adicionar Carteira</a>
            </div>

            <?php if ($success): ?>
                <div class="alert alert-success">
                    ✅ <?= htmlspecialchars($success) ?>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-error">
                    ❌ <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <?php if (count($wallets) > 0): ?>
                <?php foreach ($wallets as $wallet): ?>
                <div class="wallet-card">
                    <div class="wallet-header">
                        <div class="wallet-info">
                            <div class="wallet-icon">🔗</div>
                            <div class="wallet-details">
                                <h3><?= htmlspecialchars($wallet['label'] ?? 'Carteira sem label') ?></h3>
                                <p class="address"><?= substr($wallet['address'], 0, 10) ?>...<?= substr($wallet['address'], -8) ?></p>
                            </div>
                        </div>
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <span class="badge badge-<?= htmlspecialchars($wallet['network']) ?>">
                                <?= $network_labels[$wallet['network']] ?? ucfirst($wallet['network']) ?>
                            </span>
                            <?php if ($wallet['sync_status'] === 'syncing'): ?>
                                <span class="badge badge-warning">Sincronizando...</span>
                            <?php elseif ($wallet['sync_status'] === 'error'): ?>
                                <span class="badge badge-error">Erro</span>
                            <?php elseif ($wallet['last_sync_at']): ?>
                                <span class="badge badge-success">Sincronizado</span>
                            <?php else: ?>
                                <span class="badge badge-info">Pendente</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="wallet-stats">
                        <div class="wallet-stat">
                            <div class="wallet-stat-label">Transações</div>
                            <div class="wallet-stat-value"><?= $wallet['transaction_count'] ?></div>
                        </div>
                        <div class="wallet-stat">
                            <div class="wallet-stat-label">Último Bloco</div>
                            <div class="wallet-stat-value"><?= $wallet['last_block_synced'] ?? 'N/A' ?></div>
                        </div>
                        <div class="wallet-stat">
                            <div class="wallet-stat-label">Última Sync</div>
                            <div class="wallet-stat-value">
                                <?= $wallet['last_sync_at'] ? date('d/m/Y H:i', strtotime($wallet['last_sync_at'])) : 'Nunca' ?>
                            </div>
                        </div>
                        <div class="wallet-actions">
                            <a href="#" class="btn-icon" onclick="toggleWalletStatus(<?= $wallet['id'] ?>, <?= $wallet['is_active'] ? 0 : 1 ?>)" title="<?= $wallet['is_active'] ? 'Desativar' : 'Ativar' ?>">
                                <?= $wallet['is_active'] ? '⏸️' : '▶️' ?>
                            </a>
                            <a href="#" class="btn-icon danger" onclick="deleteWallet(<?= $wallet['id'] ?>)" title="Remover">
                                🗑️
                            </a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <p style="font-size: 4rem;">🔗</p>
                    <p>Nenhuma carteira cadastrada</p>
                    <small>Clique em "Adicionar Carteira" para começar a monitorar seu portfólio DeFi</small>
                    <br><br>
                    <a href="/main/public/add-wallet.php" class="btn btn-primary">+ Adicionar Carteira</a>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <script>
        function toggleWalletStatus(walletId, newStatus) {
            if (!confirm(newStatus ? 'Tem certeza que deseja ativar esta carteira?' : 'Tem certeza que deseja desativar esta carteira?')) {
                return;
            }

            fetch('/main/public/api/toggle-wallet.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ wallet_id: walletId, is_active: newStatus })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Erro: ' + data.message);
                }
            })
            .catch(error => {
                alert('Erro ao atualizar carteira');
                console.error('Error:', error);
            });
        }

        function deleteWallet(walletId) {
            if (!confirm('Tem certeza que deseja remover esta carteira? Esta ação não pode ser desfeita.')) {
                return;
            }

            fetch('/main/public/api/delete-wallet.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ wallet_id: walletId })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Erro: ' + data.message);
                }
            })
            .catch(error => {
                alert('Erro ao remover carteira');
                console.error('Error:', error);
            });
        }
    </script>
</body>
</html>
