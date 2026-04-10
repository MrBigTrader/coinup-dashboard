<?php
/**
 * Assets - CoinUp Dashboard
 * Lista de ativos e tokens por rede
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

    // Buscar ativos agrupados por token (com try-catch)
    $assets = [];
    try {
        $stmt = $db->prepare("
            SELECT
                t.token_symbol,
                t.token_name,
                t.token_address,
                w.network,
                COUNT(DISTINCT t.tx_hash) as transaction_count,
                COALESCE(SUM(t.value), 0) as total_value,
                tp.price_usd,
                (COALESCE(SUM(t.value), 0) * COALESCE(tp.price_usd, 0)) as value_usd
            FROM wallets w
            LEFT JOIN transactions_cache t ON w.id = t.wallet_id
                AND t.transaction_type IN ('transfer', 'swap', 'deposit')
                AND t.token_symbol IS NOT NULL
            LEFT JOIN token_prices tp ON t.token_symbol = tp.token_symbol
            WHERE w.user_id = ? AND w.is_active = 1
            GROUP BY t.token_symbol, w.network
            ORDER BY value_usd DESC
        ");
        $stmt->execute([$user_id]);
        $assets = $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Erro ao buscar assets: " . $e->getMessage());
    }

    // Resumo por rede (com try-catch)
    $networks = [];
    try {
        $stmt = $db->prepare("
            SELECT
                w.network,
                COUNT(DISTINCT w.id) as wallet_count,
                COUNT(DISTINCT t.tx_hash) as tx_count
            FROM wallets w
            LEFT JOIN transactions_cache t ON w.id = t.wallet_id
            WHERE w.user_id = ? AND w.is_active = 1
            GROUP BY w.network
        ");
        $stmt->execute([$user_id]);
        $networks = $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Erro ao buscar redes: " . $e->getMessage());
    }
} catch (Exception $e) {
    error_log("Erro no assets: " . $e->getMessage());
    $assets = [];
    $networks = [];
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assets - CoinUp</title>
    <link rel="stylesheet" href="/main/assets/css/style.css">
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
                <li><a href="/main/public/assets.php" class="active"><span>💼 Assets</span></a></li>
                <li><a href="/main/public/transactions.php"><span>📝 Transactions</span></a></li>
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
                    <h2>Meus Assets</h2>
                    <p>Visão dos seus tokens por rede</p>
                </div>
            </div>

            <!-- Resumo por Rede -->
            <div class="cards-grid">
                <?php foreach ($networks as $network): ?>
                <div class="card">
                    <div class="card-header">
                        <span class="card-title"><?= ucfirst($network['network']) ?></span>
                        <span class="card-icon">🔗</span>
                    </div>
                    <div class="card-value" style="font-size: 1.2rem;">
                        <?= $network['wallet_count'] ?> carteira(s)
                    </div>
                    <div style="font-size: 0.85rem; color: #94a3b8; margin-top: 8px;">
                        <?= $network['tx_count'] ?> transações
                    </div>
                </div>
                <?php endforeach; ?>

                <?php if (count($networks) === 0): ?>
                <div class="empty-state" style="grid-column: 1 / -1;">
                    <p style="font-size: 3rem;">🔗</p>
                    <p>Nenhuma carteira cadastrada</p>
                    <small>Adicione uma carteira para visualizar seus assets</small>
                </div>
                <?php endif; ?>
            </div>

            <!-- Tabela de Assets -->
            <div class="table-container">
                <div class="table-header">
                    <h3>Ativos Encontrados</h3>
                </div>

                <?php if (count($assets) > 0): ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Token</th>
                                <th>Rede</th>
                                <th>Saldo</th>
                                <th>Preço (USD)</th>
                                <th>Valor (USD)</th>
                                <th>Transações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($assets as $asset): ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($asset['token_symbol'] ?? 'N/A') ?></strong>
                                        <br><small style="color: #64748b;"><?= htmlspecialchars(substr($asset['token_name'] ?? '', 0, 20)) ?></small>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?= htmlspecialchars($asset['network']) ?>">
                                            <?= ucfirst($asset['network']) ?>
                                        </span>
                                    </td>
                                    <td><?= number_format($asset['total_value'], 6, ',', '.') ?></td>
                                    <td>
                                        <?php if ($asset['price_usd']): ?>
                                            $ <?= number_format($asset['price_usd'], 6, ',', '.') ?>
                                        <?php else: ?>
                                            <span style="color: #64748b;">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong>$ <?= number_format($asset['value_usd'], 2, ',', '.') ?></strong>
                                    </td>
                                    <td><?= $asset['transaction_count'] ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state">
                        <p style="font-size: 3rem;">💼</p>
                        <p>Nenhum ativo encontrado</p>
                        <small>As transações aparecerão aqui após a sincronização</small>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>
</html>
