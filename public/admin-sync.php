<?php
/**
 * Admin Sync - CoinUp
 * Status da sincronização blockchain
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/auth.php';
require_once dirname(__DIR__) . '/config/middleware.php';

Middleware::requireAuth();
Middleware::requireAdmin();

$auth = Auth::getInstance();
$user = $auth->getCurrentUser();

$sync_status = [];
$summary = ['total_wallets' => 0, 'syncing' => 0, 'errors' => 0, 'total_transactions' => 0];

try {
    $db = Database::getInstance()->getConnection();

    // Buscar status de sync de todas as wallets
    try {
        $stmt = $db->query("
            SELECT
                w.id as wallet_id,
                w.network,
                w.address,
                u.name as user_name,
                u.email as user_email,
                s.last_block_synced,
                s.last_sync_at,
                s.sync_status,
                COUNT(DISTINCT t.id) as total_transactions
            FROM wallets w
            JOIN users u ON w.user_id = u.id
            LEFT JOIN sync_state s ON w.id = s.wallet_id
            LEFT JOIN transactions_cache t ON w.id = t.wallet_id
            WHERE u.role = 'client' AND u.status != 'deleted'
            GROUP BY w.id, s.id
            ORDER BY s.last_sync_at DESC
        ");
        $sync_status = $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Erro ao buscar sync status: " . $e->getMessage());
    }

    // Resumo geral
    try {
        $stmt = $db->query("
            SELECT
                COUNT(DISTINCT w.id) as total_wallets,
                COUNT(DISTINCT CASE WHEN s.sync_status = 'syncing' THEN w.id END) as syncing,
                COUNT(DISTINCT CASE WHEN s.sync_status = 'error' THEN w.id END) as errors,
                COUNT(DISTINCT t.id) as total_transactions
            FROM wallets w
            JOIN users u ON w.user_id = u.id
            LEFT JOIN sync_state s ON w.id = s.wallet_id
            LEFT JOIN transactions_cache t ON w.id = t.wallet_id
            WHERE u.role = 'client' AND u.status != 'deleted'
        ");
        $summary = $stmt->fetch();
    } catch (Exception $e) {
        error_log("Erro ao buscar resumo: " . $e->getMessage());
    }
} catch (Exception $e) {
    error_log("Erro em admin-sync: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sincronização - Admin - CoinUp</title>
    <link rel="stylesheet" href="/main/assets/css/style.css">
    <style>
        .address {
            font-family: 'Courier New', monospace;
            font-size: 0.8rem;
            color: #64748b;
        }

        .refresh-btn {
            padding: 8px 16px;
            background: rgba(168, 85, 247, 0.2);
            border: 1px solid rgba(168, 85, 247, 0.3);
            color: #a855f7;
            border-radius: 8px;
            text-decoration: none;
            font-size: 0.9rem;
            cursor: pointer;
        }

        .refresh-btn:hover {
            background: rgba(168, 85, 247, 0.3);
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
                <li><a href="/main/public/admin.php"><span>👥 Clientes</span></a></li>
                <li><a href="/main/public/admin-wallets.php"><span>🔗 Carteiras</span></a></li>
                <li><a href="/main/public/admin-sync.php" class="active"><span>🔄 Sincronização</span></a></li>
                <li><a href="/main/public/sync-manual.php"><span>🔧 Sync Manual</span></a></li>
                <li><a href="/main/public/admin-logs.php"><span>📋 Logs</span></a></li>
            </ul>

            <div class="user-info">
                <p><strong><?= htmlspecialchars($user['name']) ?></strong></p>
                <small>Administrador</small>
                <a href="/main/public/logout.php" class="btn-logout" style="margin-top: 10px; padding: 8px 16px; background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.3); color: #fca5a5; border-radius: 8px; cursor: pointer; width: 100%; text-decoration: none; display: block; text-align: center;">Sair</a>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <div class="header">
                <div>
                    <h2>Sincronização Blockchain</h2>
                    <p>Status das sincronizações por carteira</p>
                </div>
                <a href="/main/public/admin-sync.php" class="refresh-btn">🔄 Atualizar</a>
            </div>

            <!-- Cards de Resumo -->
            <div class="cards-grid">
                <div class="card">
                    <div class="card-header">
                        <span class="card-title">Total de Carteiras</span>
                        <span class="card-icon">🔗</span>
                    </div>
                    <div class="card-value"><?= $summary['total_wallets'] ?></div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <span class="card-title">Sincronizando</span>
                        <span class="card-icon">🔄</span>
                    </div>
                    <div class="card-value" style="color: #fde047;"><?= $summary['syncing'] ?></div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <span class="card-title">Erros</span>
                        <span class="card-icon">⚠️</span>
                    </div>
                    <div class="card-value" style="color: #f87171;"><?= $summary['errors'] ?></div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <span class="card-title">Transações</span>
                        <span class="card-icon">📝</span>
                    </div>
                    <div class="card-value"><?= $summary['total_transactions'] ?></div>
                </div>
            </div>

            <!-- Tabela de Status -->
            <div class="table-container">
                <?php if (count($sync_status) > 0): ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Carteira</th>
                                <th>Cliente</th>
                                <th>Rede</th>
                                <th>Status</th>
                                <th>Último Bloco</th>
                                <th>Último Sync</th>
                                <th>Transações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($sync_status as $status): ?>
                                <tr>
                                    <td>
                                        <span class="address" title="<?= $status['address'] ?>">
                                            <?= substr($status['address'], 0, 8) ?>...<?= substr($status['address'], -6) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <strong><?= htmlspecialchars($status['user_name']) ?></strong>
                                        <br><small style="color: #64748b;"><?= htmlspecialchars($status['user_email']) ?></small>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?= htmlspecialchars($status['network']) ?>">
                                            <?= ucfirst($status['network']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php
                                        $status_class = match($status['sync_status']) {
                                            'syncing' => 'warning',
                                            'error' => 'error',
                                            default => 'success'
                                        };
                                        $status_label = match($status['sync_status']) {
                                            'syncing' => 'Sincronizando',
                                            'error' => 'Erro',
                                            default => 'Em dia'
                                        };
                                        ?>
                                        <span class="badge badge-<?= $status_class ?>">
                                            <?= $status_label ?>
                                        </span>
                                    </td>
                                    <td><?= $status['last_block_synced'] ?? '0' ?></td>
                                    <td>
                                        <?php if ($status['last_sync_at']): ?>
                                            <?= date('d/m/Y H:i', strtotime($status['last_sync_at'])) ?>
                                        <?php else: ?>
                                            <span style="color: #64748b;">Nunca</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= $status['total_transactions'] ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state">
                        <p style="font-size: 3rem;">🔄</p>
                        <p>Nenhuma carteira para sincronizar</p>
                        <small>As carteiras aparecerão aqui quando os clientes as adicionarem</small>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>
</html>
