<?php
/**
 * Transactions - CoinUp Premium Dashboard
 * Lista de todas as transações por carteira com Gráficos DCA
 */

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
    $token = $_GET['token'] ?? '';
    $limit = 50;

    // Buscar todas as transações
    $sql = "
        SELECT 
            t.*, 
            w.network, 
            w.address as wallet_address, 
            tp.price_usd as current_price
        FROM transactions_cache t
        JOIN wallets w ON t.wallet_id = w.id
        LEFT JOIN token_prices tp ON t.token_symbol = tp.token_symbol
        WHERE w.user_id = ? AND t.value > 0
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
    
    if ($token) {
        $sql .= " AND t.token_symbol = ?";
        $params[] = $token;
    }

    $sql .= " ORDER BY t.timestamp DESC LIMIT ?";
    $params[] = $limit;

    $transactions = [];
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Listas para filtros e seletor do gráfico
    $stmt = $db->prepare("SELECT DISTINCT network FROM wallets WHERE user_id = ? AND is_active = 1");
    $stmt->execute([$user_id]);
    $available_networks = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $stmt = $db->prepare("
        SELECT DISTINCT symbol FROM (
            SELECT t.token_symbol as symbol FROM transactions_cache t JOIN wallets w ON t.wallet_id = w.id WHERE w.user_id = ? AND t.token_symbol IS NOT NULL
            UNION
            SELECT wb.token_symbol as symbol FROM wallet_balances wb JOIN wallets w ON wb.wallet_id = w.id WHERE w.user_id = ? AND wb.token_symbol IS NOT NULL
        ) as tokens
        JOIN token_prices tp ON tokens.symbol = tp.token_symbol
        WHERE tp.price_usd > 0
        ORDER BY symbol ASC
    ");
    $stmt->execute([$user_id, $user_id]);
    $available_tokens = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $transaction_types = ['transfer', 'swap', 'deposit', 'withdraw', 'bridge', 'unknown'];

} catch (Exception $e) {
    error_log("Erro em transactions: " . $e->getMessage());
    $transactions = [];
    $available_networks = [];
    $available_tokens = [];
    $transaction_types = [];
}

// Helper para status
function getStatusClass($status) {
    return match($status) {
        'confirmed', 'success' => 'success',
        'pending' => 'warning',
        'failed', 'error' => 'error',
        default => 'default'
    };
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transactions - CoinUp Premium</title>
    <link rel="stylesheet" href="/main/public/assets/css/premium.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        .charts-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
            margin-bottom: 24px;
        }
        @media (max-width: 1024px) {
            .charts-grid {
                grid-template-columns: 1fr;
            }
        }
        .filter-bar {
            display: flex;
            gap: 16px;
            margin-bottom: 24px;
            flex-wrap: wrap;
        }
        .filter-select {
            background: rgba(255,255,255,0.03);
            border: 1px solid rgba(255,255,255,0.1);
            color: var(--text-primary);
            padding: 8px 16px;
            border-radius: 8px;
            outline: none;
            transition: all 0.2s;
        }
        .filter-select:hover, .filter-select:focus {
            border-color: var(--primary);
            background: rgba(255,255,255,0.08);
        }
        .filter-select option {
            background: var(--bg-dark);
        }
        
        /* Table enhancements */
        .premium-table td {
            vertical-align: middle;
        }
        .tx-icon-wrapper {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(255,255,255,0.05);
            margin-right: 12px;
        }
        .tx-type-transfer { color: #3B82F6; }
        .tx-type-swap { color: #A78BFA; }
        .tx-type-deposit { color: #10B981; }
        .tx-type-withdraw { color: #EF4444; }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="logo">
                <div class="logo-icon">
                    <i data-lucide="diamond"></i>
                </div>
                <h2>CoinUp</h2>
            </div>

            <nav class="nav-menu">
                <a href="/main/public/dashboard.php" class="nav-item">
                    <i data-lucide="layout-grid"></i>
                    <span>Overview</span>
                </a>
                <a href="/main/public/my-wallets.php" class="nav-item">
                    <i data-lucide="wallet"></i>
                    <span>Carteiras</span>
                </a>
                <a href="/main/public/assets.php" class="nav-item">
                    <i data-lucide="pie-chart"></i>
                    <span>Assets</span>
                </a>
                <a href="/main/public/transactions.php" class="nav-item active">
                    <i data-lucide="arrow-left-right"></i>
                    <span>Transactions</span>
                </a>
                <a href="/main/public/market.php" class="nav-item">
                    <i data-lucide="trending-up"></i>
                    <span>Market</span>
                </a>
            </nav>

            <div class="user-profile">
                <div class="user-avatar">
                    <i data-lucide="user"></i>
                </div>
                <div class="user-info">
                    <span class="user-name"><?= htmlspecialchars($user['name']) ?></span>
                    <span class="user-email"><?= htmlspecialchars($user['email']) ?></span>
                </div>
                <a href="/main/public/logout.php" class="btn btn-outline" style="width: 100%; margin-top: 16px; display: flex; justify-content: center;">
                    <i data-lucide="log-out" style="width: 16px; height: 16px; margin-right: 8px;"></i> Sair
                </a>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <header class="top-bar">
                <div class="page-title">
                    <h1>Histórico de Transações</h1>
                    <p>Acompanhe suas operações e a evolução do seu preço médio (DCA)</p>
                </div>
                <div class="top-bar-actions">
                    <button class="btn btn-outline" onclick="window.location.reload()">
                        <i data-lucide="refresh-cw"></i>
                    </button>
                </div>
            </header>

            <!-- Gráficos -->
            <div class="charts-grid">
                <!-- Gráfico Consolidado -->
                <div class="glass-panel" style="padding: 24px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
                        <h3 style="font-size: 1.1rem; font-weight: 600;">Evolução do Patrimônio</h3>
                    </div>
                    <div style="position: relative; height: 260px; width: 100%;">
                        <canvas id="accumulatedChart"></canvas>
                    </div>
                </div>

                <!-- Gráfico DCA por Token -->
                <div class="glass-panel" style="padding: 24px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
                        <h3 style="font-size: 1.1rem; font-weight: 600;">Preço Médio (DCA) vs Mercado</h3>
                        <select id="dcaTokenSelect" class="filter-select" style="padding: 4px 12px; font-size: 0.9rem;">
                            <option value="">Selecione um ativo...</option>
                            <?php foreach ($available_tokens as $t): ?>
                                <option value="<?= htmlspecialchars($t) ?>"><?= htmlspecialchars($t) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div style="position: relative; height: 260px; width: 100%;">
                        <canvas id="dcaChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Lista de Transações -->
            <div class="glass-panel" style="padding: 24px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 16px;">
                    <h3 style="font-size: 1.1rem; font-weight: 600;">Extrato de Operações</h3>
                    
                    <form class="filter-bar" method="GET" style="margin-bottom: 0;">
                        <select name="token" class="filter-select" onchange="this.form.submit()">
                            <option value="">Todos os Ativos</option>
                            <?php foreach ($available_tokens as $t): ?>
                                <option value="<?= htmlspecialchars($t) ?>" <?= $token === $t ? 'selected' : '' ?>><?= htmlspecialchars($t) ?></option>
                            <?php endforeach; ?>
                        </select>

                        <select name="network" class="filter-select" onchange="this.form.submit()">
                            <option value="">Todas as Redes</option>
                            <?php foreach ($available_networks as $net): ?>
                                <option value="<?= htmlspecialchars($net) ?>" <?= $network === $net ? 'selected' : '' ?>><?= ucfirst(htmlspecialchars($net)) ?></option>
                            <?php endforeach; ?>
                        </select>

                        <select name="type" class="filter-select" onchange="this.form.submit()">
                            <option value="">Todos os Tipos</option>
                            <?php foreach ($transaction_types as $tx_type): ?>
                                <option value="<?= htmlspecialchars($tx_type) ?>" <?= $type === $tx_type ? 'selected' : '' ?>><?= ucfirst(htmlspecialchars($tx_type)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                </div>

                <div class="table-container" style="overflow-x: auto;">
                    <?php if (count($transactions) > 0): ?>
                        <table class="premium-table">
                            <thead>
                                <tr>
                                    <th>Ativo / Tipo</th>
                                    <th>Data</th>
                                    <th>Quantidade</th>
                                    <th>Valor (Data)</th>
                                    <th>Valor (Atual)</th>
                                    <th>P&L</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($transactions as $tx): 
                                    $valDate = (float)($tx['usd_value_at_tx'] ?? 0);
                                    $currentPrice = (float)($tx['current_price'] ?? 0);
                                    $qty = (float)$tx['value'];
                                    
                                    $valCurrent = $qty * $currentPrice;
                                    $pnl = $valCurrent - $valDate;
                                    
                                    // P&L só faz sentido se tivermos o valor na data e for > 0
                                    $showPnl = ($valDate > 0 && $currentPrice > 0);
                                    $pnlColor = $pnl >= 0 ? 'text-green' : 'text-red';
                                    $pnlSign = $pnl >= 0 ? '+' : '';
                                    
                                    $txType = strtolower($tx['transaction_type'] ?? 'unknown');
                                    $iconName = 'arrow-right-left';
                                    if (str_contains($txType, 'deposit') || str_contains($txType, 'buy')) $iconName = 'arrow-down-to-line';
                                    if (str_contains($txType, 'withdraw') || str_contains($txType, 'sell')) $iconName = 'arrow-up-from-line';
                                ?>
                                    <tr>
                                        <td>
                                            <div style="display: flex; align-items: center;">
                                                <div class="tx-icon-wrapper">
                                                    <i data-lucide="<?= $iconName ?>" class="tx-type-<?= $txType ?>" style="width: 16px; height: 16px;"></i>
                                                </div>
                                                <div>
                                                    <div style="font-weight: 600;"><?= htmlspecialchars($tx['token_symbol'] ?? 'Token') ?></div>
                                                    <div style="font-size: 0.8rem; color: var(--text-muted);"><?= ucfirst($txType) ?> &bull; <?= ucfirst($tx['network'] ?? '') ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td style="color: var(--text-muted); font-size: 0.9rem;">
                                            <?= date('d/m/Y H:i', $tx['timestamp']) ?>
                                        </td>
                                        <td style="font-family: 'Courier New', monospace; font-weight: 500;">
                                            <?= number_format($qty, 6, '.', ',') ?>
                                        </td>
                                        <td>
                                            <?= $valDate > 0 ? '$' . number_format($valDate, 2, '.', ',') : '<span class="text-muted">-</span>' ?>
                                        </td>
                                        <td>
                                            <?= $currentPrice > 0 ? '$' . number_format($valCurrent, 2, '.', ',') : '<span class="text-muted">-</span>' ?>
                                        </td>
                                        <td>
                                            <?php if ($showPnl): ?>
                                                <span class="<?= $pnlColor ?>" style="font-weight: 600;">
                                                    <?= $pnlSign ?>$<?= number_format(abs($pnl), 2, '.', ',') ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge badge-<?= getStatusClass($tx['status'] ?? '') ?>">
                                                <?= ucfirst($tx['status'] ?? 'Completed') ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div style="text-align: center; padding: 60px 0; color: var(--text-muted);">
                            <i data-lucide="search-x" style="width: 48px; height: 48px; margin-bottom: 16px; opacity: 0.5;"></i>
                            <p>Nenhuma transação encontrada para os filtros selecionados.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <script>
        lucide.createIcons();

        document.addEventListener('DOMContentLoaded', async () => {
            const usdFormatter = new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' });
            
            // 1. Gráfico Consolidado (Aportes vs Atual)
            const initAccumulatedChart = async () => {
                try {
                    // Reutilizar a API do portfolio para pegar o history
                    const res = await fetch('/main/public/api/portfolio-data.php?section=history&period=30d');
                    const data = await res.json();
                    
                    if (data.success && data.history && data.history.length > 0 && typeof Chart !== 'undefined') {
                        const ctx = document.getElementById('accumulatedChart').getContext('2d');
                        
                        const labels = data.history.map(item => item.date.split('-').reverse().slice(0, 2).join('/'));
                        const values = data.history.map(item => Number(item.total_value_usd));
                        
                        new Chart(ctx, {
                            type: 'line',
                            data: {
                                labels: labels,
                                datasets: [
                                    {
                                        label: 'Valor Atual',
                                        data: values,
                                        borderColor: '#10B981',
                                        backgroundColor: 'rgba(16, 185, 129, 0.1)',
                                        borderWidth: 2,
                                        fill: true,
                                        tension: 0.4,
                                        pointRadius: 0
                                    }
                                ]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                interaction: { mode: 'index', intersect: false },
                                plugins: {
                                    legend: { labels: { color: '#94A3B8' } },
                                    tooltip: { backgroundColor: 'rgba(18, 24, 38, 0.9)' }
                                },
                                scales: {
                                    x: { grid: { display: false }, ticks: { color: '#94A3B8' } },
                                    y: { grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { color: '#94A3B8', callback: v => '$' + v } }
                                }
                            }
                        });
                    }
                } catch (e) {
                    console.error("Erro ao gerar gráfico consolidado:", e);
                }
            };

            // 2. Gráfico DCA (Preço Médio vs Preço Mercado)
            let dcaChartInstance = null;
            const dcaSelect = document.getElementById('dcaTokenSelect');
            
            const loadDCAChart = async (token) => {
                const ctx = document.getElementById('dcaChart').getContext('2d');
                
                if (!token) {
                    if (dcaChartInstance) dcaChartInstance.destroy();
                    document.getElementById('dcaChart').parentElement.innerHTML = '<div style="height: 100%; display: flex; align-items: center; justify-content: center; color: var(--text-muted);"><canvas id="dcaChart" style="display:none;"></canvas>Selecione um ativo para visualizar o DCA.</div>';
                    return;
                }
                
                try {
                    // Garantir que o canvas existe caso tenha sido substituído por texto
                    let canvas = document.getElementById('dcaChart');
                    if(canvas.style.display === 'none') {
                        canvas.parentElement.innerHTML = '<canvas id="dcaChart"></canvas>';
                        canvas = document.getElementById('dcaChart');
                    }
                    
                    const res = await fetch(`/main/public/api/dca-history.php?token=${token}`);
                    const data = await res.json();
                    
                    if (data.success && data.price_history && data.price_history.length > 0) {
                        if (dcaChartInstance) dcaChartInstance.destroy();
                        
                        // O eixo X será o price_history (preço de mercado diário)
                        const labels = data.price_history.map(item => item.date.split('-').reverse().slice(0, 2).join('/'));
                        const marketPrices = data.price_history.map(item => Number(item.price_usd));
                        
                        // Interpolar DCA para cada dia: o DCA de um dia é o DCA da última transação ocorrida até aquele dia
                        const dcaValues = data.price_history.map(dayItem => {
                            const dayDate = dayItem.date;
                            let currentDca = null;
                            if (data.dca_history && data.dca_history.length > 0) {
                                // Encontra o DCA mais recente que seja menor ou igual à data do price_history
                                for (let i = 0; i < data.dca_history.length; i++) {
                                    if (data.dca_history[i].date <= dayDate) {
                                        currentDca = Number(data.dca_history[i].avg_price);
                                    } else {
                                        break; // dca_history está ordenado por data
                                    }
                                }
                            }
                            // Se currentDca for nulo (antes da primeira compra), usa 0 ou null
                            return currentDca !== null ? currentDca : null;
                        });
                        
                        dcaChartInstance = new Chart(canvas.getContext('2d'), {
                            type: 'line',
                            data: {
                                labels: labels,
                                datasets: [
                                    {
                                        label: `Mercado (${token})`,
                                        data: marketPrices,
                                        borderColor: '#64748B',
                                        borderWidth: 2,
                                        borderDash: [4, 4],
                                        fill: false,
                                        tension: 0.2,
                                        pointRadius: 0,
                                        spanGaps: true
                                    },
                                    {
                                        label: `Seu Preço Médio (DCA)`,
                                        data: dcaValues,
                                        borderColor: '#A78BFA',
                                        backgroundColor: 'rgba(167, 139, 250, 0.1)',
                                        borderWidth: 3,
                                        fill: true,
                                        tension: 0.2,
                                        pointRadius: 0,
                                        spanGaps: true
                                    }
                                ]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                plugins: {
                                    legend: { labels: { color: '#94A3B8' } },
                                    tooltip: { backgroundColor: 'rgba(18, 24, 38, 0.9)' }
                                },
                                scales: {
                                    x: { grid: { display: false }, ticks: { color: '#94A3B8' } },
                                    y: { grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { color: '#94A3B8', callback: v => '$' + v } }
                                }
                            }
                        });
                    } else {
                        if (dcaChartInstance) dcaChartInstance.destroy();
                        canvas.parentElement.innerHTML = '<div style="height: 100%; display: flex; align-items: center; justify-content: center; color: var(--text-muted);"><canvas id="dcaChart" style="display:none;"></canvas>Sem histórico suficiente para este ativo.</div>';
                    }
                } catch (e) {
                    console.error("Erro ao gerar gráfico DCA:", e);
                }
            };

            dcaSelect.addEventListener('change', (e) => loadDCAChart(e.target.value));

            // Iniciar com gráficos
            initAccumulatedChart();
            
            // Auto-selecionar o primeiro token se houver
            if (dcaSelect.options.length > 1) {
                dcaSelect.selectedIndex = 1;
                loadDCAChart(dcaSelect.value);
            } else {
                loadDCAChart('');
            }
        });
    </script>
</body>
</html>
