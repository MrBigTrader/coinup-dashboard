<?php
/**
 * Admin Client View - CoinUp
 * Visualizar detalhes de um cliente
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

$id = $_GET['id'] ?? 0;

try {
    $db = Database::getInstance()->getConnection();

    // Buscar dados do cliente
    $stmt = $db->prepare("
        SELECT
            id, name, email, role, status, created_at, updated_at
        FROM users
        WHERE id = ? AND role = 'client' AND status != 'deleted'
    ");
    $stmt->execute([$id]);
    $client = $stmt->fetch();

    if (!$client) {
        header('Location: /main/public/admin.php');
        exit;
    }

    // Buscar carteiras do cliente
    $wallets = [];
    try {
        $stmt = $db->prepare("
            SELECT
                id, network, address, label, is_active, created_at
            FROM wallets
            WHERE user_id = ?
            ORDER BY created_at DESC
        ");
        $stmt->execute([$id]);
        $wallets = $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Erro ao buscar carteiras: " . $e->getMessage());
    }

    // Resumo do cliente com patrimônio
    $summary = ['wallet_count' => 0, 'transaction_count' => 0, 'total_value_usd' => 0];
    $total_value_brl = 0;
    try {
        $stmt = $db->prepare("
            SELECT
                COUNT(DISTINCT w.id) as wallet_count,
                COUNT(DISTINCT t.id) as transaction_count,
                COALESCE(SUM(
                    CASE 
                        WHEN tp.price_usd IS NOT NULL THEN t.value * tp.price_usd
                        ELSE 0
                    END
                ), 0) as total_value_usd
            FROM wallets w
            LEFT JOIN transactions_cache t ON w.id = t.wallet_id
                AND t.transaction_type IN ('transfer', 'swap', 'deposit')
            LEFT JOIN token_prices tp ON t.token_symbol = tp.token_symbol
            WHERE w.user_id = ? AND w.is_active = 1
        ");
        $stmt->execute([$id]);
        $summary = $stmt->fetch();

        // Calcular valor em BRL
        $stmt = $db->prepare("SELECT price_brl, price_usd FROM token_prices WHERE token_symbol = 'ETH' LIMIT 1");
        $stmt->execute();
        $eth_price = $stmt->fetch();
        if ($eth_price && $eth_price['price_brl'] && $eth_price['price_usd'] > 0) {
            $exchange_rate = $eth_price['price_brl'] / $eth_price['price_usd'];
            $total_value_brl = $summary['total_value_usd'] * $exchange_rate;
        }
    } catch (Exception $e) {
        error_log("Erro ao buscar resumo: " . $e->getMessage());
    }
} catch (Exception $e) {
    error_log("Erro em admin-client-view: " . $e->getMessage());
    header('Location: /main/public/admin.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($client['name']) ?> - Admin - CoinUp</title>
    <link rel="stylesheet" href="/main/assets/css/style.css">
    <style>
        .address {
            font-family: 'Courier New', monospace;
            font-size: 0.85rem;
            color: #64748b;
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }

        .detail-row:last-child {
            border-bottom: none;
        }

        .detail-label {
            color: #94a3b8;
            font-weight: 500;
        }

        .detail-value {
            color: #e2e8f0;
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
                    <h2><?= htmlspecialchars($client['name']) ?></h2>
                    <p>Detalhes do cliente</p>
                </div>
                <div style="display: flex; gap: 12px;">
                    <a href="/main/public/admin-client-edit.php?id=<?= $client['id'] ?>" class="btn btn-primary">Editar</a>
                    <a href="/main/public/admin.php" class="btn btn-secondary">Voltar</a>
                </div>
            </div>

            <!-- Cards de Resumo -->
            <div class="cards-grid">
                <div class="card">
                    <div class="card-header">
                        <span class="card-title">Patrimônio Total</span>
                        <span class="card-icon">💰</span>
                    </div>
                    <div class="card-value" style="font-size: 1.5rem;">
                        $ <?= number_format($summary['total_value_usd'] ?? 0, 2, ',', '.') ?>
                    </div>
                    <div style="font-size: 0.85rem; color: #94a3b8; margin-top: 5px;">
                        ≈ R$ <?= number_format($total_value_brl ?? 0, 2, ',', '.') ?>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <span class="card-title">Carteiras</span>
                        <span class="card-icon">🔗</span>
                    </div>
                    <div class="card-value"><?= $summary['wallet_count'] ?></div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <span class="card-title">Transações</span>
                        <span class="card-icon">📝</span>
                    </div>
                    <div class="card-value"><?= $summary['transaction_count'] ?></div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <span class="card-title">Status</span>
                        <span class="card-icon">✓</span>
                    </div>
                    <div class="card-value">
                        <span class="badge badge-<?= $client['status'] === 'active' ? 'success' : 'warning' ?>">
                            <?= ucfirst($client['status']) ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Detalhes -->
            <div class="card" style="margin-bottom: 30px;">
                <h3 style="color: #fff; margin-bottom: 20px;">Informações do Cliente</h3>
                <div class="detail-row">
                    <span class="detail-label">Nome</span>
                    <span class="detail-value"><?= htmlspecialchars($client['name']) ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">E-mail</span>
                    <span class="detail-value"><?= htmlspecialchars($client['email']) ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Perfil</span>
                    <span class="detail-value"><?= ucfirst($client['role']) ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Criado em</span>
                    <span class="detail-value"><?= date('d/m/Y H:i', strtotime($client['created_at'])) ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Atualizado em</span>
                    <span class="detail-value"><?= $client['updated_at'] ? date('d/m/Y H:i', strtotime($client['updated_at'])) : '-' ?></span>
                </div>
            </div>

            <!-- Carteiras -->
            <div class="table-container">
                <div class="table-header">
                    <h3>Carteiras do Cliente</h3>
                </div>

                <?php if (count($wallets) > 0): ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Rede</th>
                                <th>Endereço</th>
                                <th>Label</th>
                                <th>Status</th>
                                <th>Criada em</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($wallets as $wallet): ?>
                                <tr>
                                    <td>
                                        <span class="badge badge-<?= htmlspecialchars($wallet['network']) ?>">
                                            <?= ucfirst($wallet['network']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="address" title="<?= $wallet['address'] ?>">
                                            <?= substr($wallet['address'], 0, 10) ?>...<?= substr($wallet['address'], -8) ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars($wallet['label'] ?? '-') ?></td>
                                    <td>
                                        <span class="badge badge-<?= $wallet['is_active'] ? 'success' : 'error' ?>">
                                            <?= $wallet['is_active'] ? 'Ativa' : 'Inativa' ?>
                                        </span>
                                    </td>
                                    <td><?= date('d/m/Y', strtotime($wallet['created_at'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state">
                        <p style="font-size: 3rem;">🔗</p>
                        <p>Nenhuma carteira cadastrada</p>
                        <small>Este cliente ainda não adicionou nenhuma carteira</small>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>
</html>
