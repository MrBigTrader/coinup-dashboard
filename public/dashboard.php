<?php
/**
 * Dashboard do Cliente - CoinUp
 * Visão geral do patrimônio
 */

// Habilitar log de erros para debug
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/auth.php';
require_once dirname(__DIR__) . '/config/middleware.php';

// Requer autenticação
Middleware::requireAuth();

$auth = Auth::getInstance();

// Verificar se é cliente - se for admin, redireciona
if ($auth->isAdmin()) {
    header('Location: /main/public/admin.php');
    exit;
}

// Se não é cliente nem admin, erro
if (!$auth->isClient()) {
    // Usuário não tem perfil válido, fazer logout
    $auth->logout();
    header('Location: /main/public/login.php');
    exit;
}

$user = $auth->getCurrentUser();

try {
    $db = Database::getInstance()->getConnection();
    $user_id = $auth->getCurrentUserId();

    // Buscar total de patrimônio: soma de wallet_balances (saldo real da blockchain)
    $stmt = $db->prepare("
        SELECT
            (SELECT COUNT(DISTINCT id) FROM wallets WHERE user_id = ? AND is_active = 1) as wallet_count,
            COALESCE(SUM(wb.balance_usd), 0) as total_value_usd
        FROM wallets w
        JOIN wallet_balances wb ON w.id = wb.wallet_id
        WHERE w.user_id = ? AND w.is_active = 1
    ");
    $stmt->execute([$user_id, $user_id]);
    $summary = $stmt->fetch();

    // Buscar preço do ETH para converter USD para BRL (aproximação)
    $total_value_brl = 0;
    try {
        $stmt = $db->prepare("SELECT price_brl FROM token_prices WHERE token_symbol = 'ETH' LIMIT 1");
        $stmt->execute();
        $eth_price = $stmt->fetch();
        if ($eth_price && $eth_price['price_brl']) {
            // Usar taxa de câmbio implícita do ETH
            $stmt = $db->prepare("SELECT price_usd FROM token_prices WHERE token_symbol = 'ETH' LIMIT 1");
            $stmt->execute();
            $eth_usd = $stmt->fetch();
            if ($eth_usd && $eth_usd['price_usd'] > 0) {
                $exchange_rate = $eth_price['price_brl'] / $eth_usd['price_usd'];
                $total_value_brl = $summary['total_value_usd'] * $exchange_rate;
            }
        }
    } catch (Exception $e) {
        error_log("Erro ao calcular BRL: " . $e->getMessage());
    }

    // Buscar últimas transações (com verificação de tabela vazia)
    $recent_transactions = [];
    try {
        $stmt = $db->prepare("
            SELECT t.*, w.network, w.address as wallet_address
            FROM transactions_cache t
            JOIN wallets w ON t.wallet_id = w.id
            WHERE w.user_id = ?
            ORDER BY t.timestamp DESC
            LIMIT 5
        ");
        $stmt->execute([$user_id]);
        $recent_transactions = $stmt->fetchAll();
    } catch (Exception $e) {
        // Tabela pode não existir ou estar vazia
        error_log("Erro ao buscar transações: " . $e->getMessage());
        $recent_transactions = [];
    }
} catch (Exception $e) {
    error_log("Erro no dashboard: " . $e->getMessage());
    $summary = ['wallet_count' => 0, 'total_value_usd' => 0];
    $total_value_brl = 0;
    $recent_transactions = [];
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - CoinUp</title>
    <link rel="stylesheet" href="/main/assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
            min-height: 100vh;
            color: #e2e8f0;
        }

        .dashboard-container {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar */
        .sidebar {
            width: 260px;
            background: rgba(0, 0, 0, 0.3);
            backdrop-filter: blur(10px);
            border-right: 1px solid rgba(255, 255, 255, 0.05);
            padding: 20px;
            display: flex;
            flex-direction: column;
        }

        .logo {
            padding: 20px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            margin-bottom: 20px;
        }

        .logo h1 {
            color: #fff;
            font-size: 1.8rem;
            background: linear-gradient(135deg, #a855f7, #3b82f6);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .nav-menu {
            list-style: none;
            flex: 1;
        }

        .nav-menu li {
            margin-bottom: 8px;
        }

        .nav-menu a {
            display: flex;
            align-items: center;
            padding: 12px 16px;
            color: #94a3b8;
            text-decoration: none;
            border-radius: 10px;
            transition: all 0.3s ease;
        }

        .nav-menu a:hover,
        .nav-menu a.active {
            background: rgba(168, 85, 247, 0.1);
            color: #a855f7;
        }

        .nav-menu a span {
            margin-left: 10px;
        }

        .user-info {
            padding: 15px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 10px;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .user-info p {
            font-size: 0.9rem;
            color: #e2e8f0;
        }

        .user-info small {
            color: #64748b;
        }

        .btn-logout {
            margin-top: 10px;
            padding: 8px 16px;
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #fca5a5;
            border-radius: 8px;
            cursor: pointer;
            width: 100%;
            transition: all 0.3s ease;
        }

        .btn-logout:hover {
            background: rgba(239, 68, 68, 0.2);
        }

        /* Main Content */
        .main-content {
            flex: 1;
            padding: 30px;
            overflow-y: auto;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .header h2 {
            color: #fff;
            font-size: 1.8rem;
        }

        .header p {
            color: #94a3b8;
        }

        /* Cards */
        .cards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .card {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            padding: 25px;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .card-title {
            color: #94a3b8;
            font-size: 0.9rem;
        }

        .card-icon {
            font-size: 1.5rem;
        }

        .card-value {
            color: #fff;
            font-size: 2rem;
            font-weight: 700;
        }

        .card-change {
            font-size: 0.85rem;
            margin-top: 8px;
        }

        .card-change.positive {
            color: #4ade80;
        }

        .card-change.negative {
            color: #f87171;
        }

        /* Table */
        .table-container {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            padding: 25px;
        }

        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .table-header h3 {
            color: #fff;
            font-size: 1.2rem;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }

        th {
            color: #94a3b8;
            font-weight: 500;
            font-size: 0.85rem;
        }

        td {
            color: #e2e8f0;
            font-size: 0.9rem;
        }

        tr:hover td {
            background: rgba(255, 255, 255, 0.02);
        }

        .badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .badge-ethereum { background: rgba(100, 100, 255, 0.2); color: #a5b4fc; }
        .badge-bnb { background: rgba(255, 200, 0, 0.2); color: #fde047; }
        .badge-arbitrum { background: rgba(36, 100, 255, 0.2); color: #60a5fa; }
        .badge-base { background: rgba(0, 100, 255, 0.2); color: #3b82f6; }
        .badge-polygon { background: rgba(130, 50, 255, 0.2); color: #a855f7; }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #64748b;
        }

        .empty-state p {
            margin-top: 10px;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .dashboard-container {
                flex-direction: column;
            }

            .sidebar {
                width: 100%;
                border-right: none;
                border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            }

            .nav-menu {
                display: flex;
                overflow-x: auto;
                gap: 10px;
            }

            .nav-menu li {
                margin-bottom: 0;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="logo">
                <h1>🪙 CoinUp</h1>
            </div>

            <ul class="nav-menu">
                <li><a href="/main/public/dashboard.php" class="active"><span>📊 Overview</span></a></li>
                <li><a href="/main/public/my-wallets.php"><span>🔗 Minhas Carteiras</span></a></li>
                <li><a href="/main/public/assets.php"><span>💼 Assets</span></a></li>
                <li><a href="/main/public/transactions.php"><span>📝 Transactions</span></a></li>
                <li><a href="/main/public/market.php"><span>📈 Market</span></a></li>
            </ul>

            <div class="user-info">
                <p><strong><?= htmlspecialchars($user['name']) ?></strong></p>
                <small><?= htmlspecialchars($user['email']) ?></small>
                <a href="/main/public/logout.php" class="btn-logout">Sair</a>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <div class="header">
                <div>
                    <h2>Visão Geral</h2>
                    <p>Bem-vindo de volta, <?= htmlspecialchars($user['name']) ?>!</p>
                </div>
            </div>

            <!-- Cards de Resumo -->
            <div class="cards-grid">
                <div class="card">
                    <div class="card-header">
                        <span class="card-title">Patrimônio Total (USD)</span>
                        <span class="card-icon">💵</span>
                    </div>
                    <div class="card-value">
                        $ <?= number_format($summary['total_value_usd'] ?? 0, 2, ',', '.') ?>
                    </div>
                    <div class="card-change positive">
                        <span>≈ R$ <?= number_format($total_value_brl ?? 0, 2, ',', '.') ?></span>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <span class="card-title">Carteiras Ativas</span>
                        <span class="card-icon">🔗</span>
                    </div>
                    <div class="card-value">
                        <?= $summary['wallet_count'] ?? 0 ?>
                    </div>
                    <div class="card-change">
                        <span>Redes EVM</span>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <span class="card-title">Última Sincronização</span>
                        <span class="card-icon">🔄</span>
                    </div>
                    <div class="card-value" style="font-size: 1.2rem;">
                        Em breve
                    </div>
                    <div class="card-change">
                        <span>Aguardando sync</span>
                    </div>
                </div>
            </div>

            <!-- Últimas Transações -->
            <div class="table-container">
                <div class="table-header">
                    <h3>Últimas Transações</h3>
                    <a href="/main/public/transactions.php" style="color: #a855f7; text-decoration: none; font-size: 0.9rem;">Ver todas →</a>
                </div>

                <?php if (count($recent_transactions) > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Data</th>
                                <th>Token</th>
                                <th>Rede</th>
                                <th>Tipo</th>
                                <th>Valor</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_transactions as $tx): ?>
                                <tr>
                                    <td><?= date('d/m/Y H:i', $tx['timestamp']) ?></td>
                                    <td><?= htmlspecialchars($tx['token_symbol'] ?? 'N/A') ?></td>
                                    <td>
                                        <span class="badge badge-<?= htmlspecialchars($tx['network']) ?>">
                                            <?= ucfirst($tx['network']) ?>
                                        </span>
                                    </td>
                                    <td><?= ucfirst($tx['transaction_type']) ?></td>
                                    <td><?= number_format($tx['value'], 6, ',', '.') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state">
                        <p style="font-size: 3rem;">📭</p>
                        <p>Nenhuma transação encontrada</p>
                        <small>Adicione uma carteira para começar a sincronizar</small>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>
</html>
