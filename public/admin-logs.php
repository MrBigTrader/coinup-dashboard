<?php
/**
 * Admin Logs - CoinUp
 * Logs de sincronização e atividades
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

$logs = [];

try {
    $db = Database::getInstance()->getConnection();

    // Filtros
    $status = $_GET['status'] ?? '';
    $limit = 100;

    // Buscar logs de sincronização
    $sql = "
        SELECT
            sl.*,
            w.address as wallet_address,
            w.network,
            u.name as user_name,
            u.email as user_email
        FROM sync_logs sl
        JOIN wallets w ON sl.wallet_id = w.id
        JOIN users u ON w.user_id = u.id
        WHERE u.role = 'client' AND u.status != 'deleted'
    ";
    $params = [];

    if ($status) {
        $sql .= " AND sl.status = ?";
        $params[] = $status;
    }

    $sql .= " ORDER BY sl.executed_at DESC LIMIT ?";
    $params[] = $limit;

    try {
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $logs = $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Erro ao buscar logs: " . $e->getMessage());
    }
} catch (Exception $e) {
    error_log("Erro em admin-logs: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logs - Admin - CoinUp</title>
    <link rel="stylesheet" href="/main/assets/css/style.css">
    <style>
        .address {
            font-family: 'Courier New', monospace;
            font-size: 0.8rem;
            color: #64748b;
        }

        .filters {
            display: flex;
            gap: 16px;
            margin-bottom: 20px;
        }

        .filters select {
            padding: 8px 16px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            color: #e2e8f0;
            cursor: pointer;
        }

        .error-message {
            color: #f87171;
            font-size: 0.85rem;
            max-width: 300px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
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
                <li><a href="/main/public/admin-sync.php"><span>🔄 Sincronização</span></a></li>
                <li><a href="/main/public/sync-manual.php"><span>🔧 Sync Manual</span></a></li>
                <li><a href="/main/public/admin-logs.php" class="active"><span>📋 Logs</span></a></li>
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
                    <h2>Logs de Sincronização</h2>
                    <p>Histórico de execuções dos workers</p>
                </div>
            </div>

            <!-- Filtros -->
            <form class="filters" method="GET">
                <select name="status" onchange="this.form.submit()">
                    <option value="">Todos os Status</option>
                    <option value="success" <?= $status === 'success' ? 'selected' : '' ?>>Sucesso</option>
                    <option value="error" <?= $status === 'error' ? 'selected' : '' ?>>Erro</option>
                    <option value="partial" <?= $status === 'partial' ? 'selected' : '' ?>>Parcial</option>
                </select>
            </form>

            <!-- Tabela de Logs -->
            <div class="table-container">
                <?php if (count($logs) > 0): ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Data/Hora</th>
                                <th>Carteira</th>
                                <th>Cliente</th>
                                <th>Rede</th>
                                <th>Status</th>
                                <th>Blocos</th>
                                <th>Transações</th>
                                <th>Duração</th>
                                <th>Erro</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($logs as $log): ?>
                                <tr>
                                    <td><?= date('d/m/Y H:i:s', strtotime($log['executed_at'])) ?></td>
                                    <td>
                                        <span class="address" title="<?= $log['wallet_address'] ?>">
                                            <?= substr($log['wallet_address'], 0, 8) ?>...<?= substr($log['wallet_address'], -6) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <strong><?= htmlspecialchars($log['user_name']) ?></strong>
                                        <br><small style="color: #64748b;"><?= htmlspecialchars($log['user_email']) ?></small>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?= htmlspecialchars($log['network']) ?>">
                                            <?= ucfirst($log['network']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php
                                        $status_class = match($log['status']) {
                                            'success' => 'success',
                                            'error' => 'error',
                                            'partial' => 'warning',
                                        };
                                        $status_label = match($log['status']) {
                                            'success' => 'Sucesso',
                                            'error' => 'Erro',
                                            'partial' => 'Parcial',
                                        };
                                        ?>
                                        <span class="badge badge-<?= $status_class ?>">
                                            <?= $status_label ?>
                                        </span>
                                    </td>
                                    <td><?= $log['blocks_processed'] ?></td>
                                    <td><?= $log['transactions_found'] ?></td>
                                    <td>
                                        <?php if ($log['duration_seconds']): ?>
                                            <?= $log['duration_seconds'] ?>s
                                        <?php else: ?>
                                            <span style="color: #64748b;">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($log['error_message']): ?>
                                            <span class="error-message" title="<?= htmlspecialchars($log['error_message']) ?>">
                                                <?= htmlspecialchars($log['error_message']) ?>
                                            </span>
                                        <?php else: ?>
                                            <span style="color: #64748b;">-</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state">
                        <p style="font-size: 3rem;">📋</p>
                        <p>Nenhum log encontrado</p>
                        <small>Os logs aparecerão aqui após as primeiras sincronizações</small>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>
</html>
