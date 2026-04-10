<?php
/**
 * Transactions - CoinUp Dashboard
 * Lista de todas as transações por carteira
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

try {
    $db = Database::getInstance()->getConnection();
    $user_id = $auth->getCurrentUserId();

    // Filtros
    $network = $_GET['network'] ?? '';
    $type = $_GET['type'] ?? '';
    $limit = 50;

    // Buscar todas as transações
    $sql = "
        SELECT t.*, w.network, w.address as wallet_address, w.label as wallet_label
        FROM wallets w
        LEFT JOIN transactions_cache t ON w.id = t.wallet_id
        WHERE w.user_id = ?
    ";
    $params = [$user_id];

    if ($network) {
        $sql .= " AND w.network = ?";
        $params[] = $network;
    }

    if ($type) {
        $sql .= " AND t.transaction_type = ?";
        $params[] = $type;
    }

    $sql .= " ORDER BY t.timestamp DESC LIMIT ?";
    $params[] = $limit;

    $transactions = [];
    try {
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $transactions = $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Erro ao buscar transações: " . $e->getMessage());
    }

    // Buscar redes do usuário para filtro
    $available_networks = [];
    try {
        $stmt = $db->prepare("SELECT DISTINCT network FROM wallets WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $available_networks = $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) {
        error_log("Erro ao buscar redes: " . $e->getMessage());
    }

    // Tipos de transação
    $transaction_types = ['transfer', 'swap', 'deposit', 'withdraw', 'bridge', 'unknown'];
} catch (Exception $e) {
    error_log("Erro em transactions: " . $e->getMessage());
    $transactions = [];
    $available_networks = [];
    $transaction_types = ['transfer', 'swap', 'deposit', 'withdraw', 'bridge', 'unknown'];
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transações - CoinUp</title>
    <link rel="stylesheet" href="/main/assets/css/style.css">
    <style>
        .filters {
            display: flex;
            gap: 16px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .filters select {
            padding: 8px 16px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            color: #e2e8f0;
            cursor: pointer;
        }

        .tx-hash {
            font-family: 'Courier New', monospace;
            font-size: 0.85rem;
            color: #a855f7;
            text-decoration: none;
        }

        .tx-hash:hover {
            text-decoration: underline;
        }

        .address {
            font-family: 'Courier New', monospace;
            font-size: 0.8rem;
            color: #64748b;
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
                <li><a href="/main/public/my-wallets.php"><span>🔗 Minhas Carteiras</span></a></li>
                <li><a href="/main/public/assets.php"><span>💼 Assets</span></a></li>
                <li><a href="/main/public/transactions.php" class="active"><span>📝 Transactions</span></a></li>
                <li><a href="/main/public/market.php"><span>📈 Market</span></a></li>
            </ul>

            <div class="user-info">
                <p><strong><?= htmlspecialchars($user['name']) ?></strong></p>
                <small><?= htmlspecialchars($user['email']) ?></small>
                <a href="/main/public/logout.php" class="btn-logout" style="margin-top: 10px; padding: 8px 16px; background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.3); color: #fca5a5; border-radius: 8px; cursor: pointer; width: 100%; text-decoration: none; display: block; text-align: center;">Sair</a>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <div class="header">
                <div>
                    <h2>Transações</h2>
                    <p>Histórico de todas as suas transações</p>
                </div>
            </div>

            <!-- Filtros -->
            <form class="filters" method="GET">
                <select name="network" onchange="this.form.submit()">
                    <option value="">Todas as Redes</option>
                    <?php foreach ($available_networks as $net): ?>
                        <option value="<?= $net ?>" <?= $network === $net ? 'selected' : '' ?>>
                            <?= ucfirst($net) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <select name="type" onchange="this.form.submit()">
                    <option value="">Todos os Tipos</option>
                    <?php foreach ($transaction_types as $tx_type): ?>
                        <option value="<?= $tx_type ?>" <?= $type === $tx_type ? 'selected' : '' ?>>
                            <?= ucfirst($tx_type) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>

            <!-- Tabela de Transações -->
            <div class="table-container">
                <?php if (count($transactions) > 0): ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Data</th>
                                <th>Token</th>
                                <th>Rede</th>
                                <th>Tipo</th>
                                <th>De/Para</th>
                                <th>Valor</th>
                                <th>TX Hash</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($transactions as $tx): ?>
                                <tr>
                                    <td><?= date('d/m/Y H:i', $tx['timestamp']) ?></td>
                                    <td>
                                        <strong><?= htmlspecialchars($tx['token_symbol'] ?? 'N/A') ?></strong>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?= htmlspecialchars($tx['network']) ?>">
                                            <?= ucfirst($tx['network']) ?>
                                        </span>
                                    </td>
                                    <td><?= ucfirst($tx['transaction_type']) ?></td>
                                    <td>
                                        <span class="address" title="<?= $tx['from_address'] ?>">
                                            <?= substr($tx['from_address'], 0, 6) ?>...<?= substr($tx['from_address'], -4) ?>
                                        </span>
                                        <span style="color: #64748b;"> → </span>
                                        <span class="address" title="<?= $tx['to_address'] ?>">
                                            <?= substr($tx['to_address'], 0, 6) ?>...<?= substr($tx['to_address'], -4) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <strong><?= number_format($tx['value'], 6, ',', '.') ?></strong>
                                    </td>
                                    <td>
                                        <a href="#" class="tx-hash" title="<?= $tx['tx_hash'] ?>">
                                            <?= substr($tx['tx_hash'], 0, 10) ?>...
                                        </a>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?= $tx['status'] === 'confirmed' ? 'success' : ($tx['status'] === 'pending' ? 'warning' : 'error') ?>">
                                            <?= ucfirst($tx['status']) ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state">
                        <p style="font-size: 3rem;">📝</p>
                        <p>Nenhuma transação encontrada</p>
                        <small>As transações aparecerão aqui após a sincronização</small>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>
</html>
