<?php
/**
 * Dashboard do Cliente - CoinUp
 * Visão geral do patrimônio (Premium V2)
 */

require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/auth.php';
require_once dirname(__DIR__) . '/config/middleware.php';

Middleware::requireAuth();

$auth = Auth::getInstance();
if ($auth->isAdmin()) {
    header('Location: /main/public/admin.php');
    exit;
}
if (!$auth->isClient()) {
    $auth->logout();
    header('Location: /main/public/login.php');
    exit;
}

$user = $auth->getCurrentUser();
$user_id = $auth->getCurrentUserId();

try {
    $db = Database::getInstance()->getConnection();

    // Buscar carteiras
    $stmt = $db->prepare("SELECT COUNT(DISTINCT id) as wallet_count FROM wallets WHERE user_id = ? AND is_active = 1");
    $stmt->execute([$user_id]);
    $wallet_count = $stmt->fetchColumn() ?: 0;

    // Buscar última sincronização real
    $stmt = $db->prepare("SELECT MAX(ss.last_sync_at) FROM sync_state ss JOIN wallets w ON ss.wallet_id = w.id WHERE w.user_id = ? AND w.is_active = 1");
    $stmt->execute([$user_id]);
    $last_sync_raw = $stmt->fetchColumn();
    $last_sync = $last_sync_raw ? date('d/m/Y H:i', strtotime($last_sync_raw)) : 'Aguardando sync';

    // Buscar transações
    $stmt = $db->prepare("
        SELECT t.*, w.network
        FROM transactions_cache t
        JOIN wallets w ON t.wallet_id = w.id
        WHERE w.user_id = ?
        ORDER BY t.timestamp DESC
        LIMIT 5
    ");
    $stmt->execute([$user_id]);
    $recent_transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    error_log("Erro no dashboard: " . $e->getMessage());
    $wallet_count = 0;
    $last_sync = 'Erro ao carregar';
    $recent_transactions = [];
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - CoinUp Premium</title>
    <link rel="stylesheet" href="/main/public/assets/css/premium.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        .top-holdings-list {
            margin-top: 16px;
        }
        .holding-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid var(--border-light);
        }
        .holding-item:last-child {
            border-bottom: none;
        }
        .holding-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .holding-icon {
            width: 32px;
            height: 32px;
            background: rgba(255,255,255,0.1);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
            font-weight: bold;
        }
        .holding-details h4 {
            font-size: 0.9rem;
            margin-bottom: 2px;
        }
        .holding-details span {
            font-size: 0.75rem;
            color: var(--text-muted);
        }
        .holding-value {
            text-align: right;
        }
        .holding-value h4 {
            font-size: 0.95rem;
            margin-bottom: 2px;
        }
        .holding-value span {
            font-size: 0.75rem;
        }
        
        /* Grid Layout Fix */
        .dashboard-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 24px;
            margin-bottom: 32px;
        }
        @media (max-width: 1024px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
        }
        
        .loading-skeleton {
            background: linear-gradient(90deg, rgba(255,255,255,0.05) 25%, rgba(255,255,255,0.1) 50%, rgba(255,255,255,0.05) 75%);
            background-size: 200% 100%;
            animation: loading 1.5s infinite;
            border-radius: 4px;
            height: 20px;
            width: 100%;
        }
        @keyframes loading {
            0% { background-position: 200% 0; }
            100% { background-position: -200% 0; }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar Reutilizável (Para simplificar no teste, mantida inline) -->
        <aside class="sidebar glass-panel" style="border-radius: 0; border-right: 1px solid var(--border-light); border-top: none; border-bottom: none; border-left: none; padding: 24px;">
            <div class="logo" style="margin-bottom: 32px; display: flex; align-items: center; gap: 12px;">
                <div style="background: linear-gradient(135deg, var(--primary), var(--secondary)); padding: 8px; border-radius: 12px;">
                    <i data-lucide="gem" color="white" size="24"></i>
                </div>
                <h1 class="text-gradient" style="font-size: 1.5rem; font-weight: 700;">CoinUp</h1>
            </div>

            <ul class="nav-menu" style="list-style: none;">
                <li style="margin-bottom: 8px;"><a href="/main/public/dashboard.php" style="display: flex; align-items: center; gap: 12px; padding: 12px 16px; color: var(--primary); background: var(--primary-glow); border-radius: var(--radius-md); text-decoration: none; font-weight: 500; transition: var(--transition);"><i data-lucide="layout-dashboard" size="20"></i> Overview</a></li>
                <li style="margin-bottom: 8px;"><a href="/main/public/my-wallets.php" style="display: flex; align-items: center; gap: 12px; padding: 12px 16px; color: var(--text-secondary); text-decoration: none; transition: var(--transition);"><i data-lucide="wallet" size="20"></i> Carteiras</a></li>
                <li style="margin-bottom: 8px;"><a href="/main/public/assets.php" style="display: flex; align-items: center; gap: 12px; padding: 12px 16px; color: var(--text-secondary); text-decoration: none; transition: var(--transition);"><i data-lucide="pie-chart" size="20"></i> Assets</a></li>
                <li style="margin-bottom: 8px;"><a href="/main/public/transactions.php" style="display: flex; align-items: center; gap: 12px; padding: 12px 16px; color: var(--text-secondary); text-decoration: none; transition: var(--transition);"><i data-lucide="arrow-left-right" size="20"></i> Transactions</a></li>
                <li style="margin-bottom: 8px;"><a href="/main/public/market.php" style="display: flex; align-items: center; gap: 12px; padding: 12px 16px; color: var(--text-secondary); text-decoration: none; transition: var(--transition);"><i data-lucide="trending-up" size="20"></i> Market</a></li>
            </ul>

            <div class="user-info glass-panel" style="margin-top: auto; padding: 16px; border-radius: var(--radius-md);">
                <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 12px;">
                    <div style="width: 40px; height: 40px; border-radius: 50%; background: var(--border-light); display: flex; align-items: center; justify-content: center;">
                        <i data-lucide="user" size="20"></i>
                    </div>
                    <div>
                        <p style="font-weight: 600; font-size: 0.9rem;"><?= htmlspecialchars($user['name']) ?></p>
                        <p class="text-muted" style="font-size: 0.75rem;"><?= htmlspecialchars($user['email']) ?></p>
                    </div>
                </div>
                <a href="/main/public/logout.php" class="btn-glass" style="display: block; text-align: center; width: 100%; text-decoration: none; font-size: 0.85rem;"><i data-lucide="log-out" size="14" style="vertical-align: middle; margin-right: 6px;"></i> Sair</a>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <header style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 32px;" class="animate-fade-in">
                <div>
                    <h2 style="font-size: 1.8rem; margin-bottom: 4px;">Visão Geral do Portfólio</h2>
                    <p class="text-muted">Acompanhe seu patrimônio em tempo real (Powered by WP3 Data Engine)</p>
                </div>
                <div style="display: flex; gap: 12px;">
                    <button class="btn-glass" onclick="location.reload()"><i data-lucide="refresh-cw" size="16"></i></button>
                    <a href="/main/public/my-wallets.php" class="btn-primary" style="text-decoration: none; display: inline-block;"><i data-lucide="plus" size="16" style="vertical-align: middle; margin-right: 6px;"></i> Adicionar Wallet</a>
                </div>
            </header>

            <!-- Stats Grid -->
            <div class="stats-grid animate-fade-in delay-1">
                <div class="stat-card glass-panel">
                    <div class="stat-title">
                        <span>Patrimônio Total</span>
                        <div style="background: rgba(110, 86, 207, 0.2); padding: 8px; border-radius: 8px; color: var(--primary);">
                            <i data-lucide="dollar-sign" size="20"></i>
                        </div>
                    </div>
                    <div class="stat-value" id="total-usd">
                        <div class="loading-skeleton" style="width: 150px; height: 36px;"></div>
                    </div>
                    <div id="total-brl" class="text-muted" style="font-weight: 500;">
                        <div class="loading-skeleton" style="width: 100px; margin-top: 8px;"></div>
                    </div>
                </div>

                <div class="stat-card glass-panel">
                    <div class="stat-title">
                        <span>P&L 24h</span>
                        <div style="background: rgba(16, 185, 129, 0.2); padding: 8px; border-radius: 8px; color: var(--accent-green);">
                            <i data-lucide="trending-up" size="20"></i>
                        </div>
                    </div>
                    <div class="stat-value" id="pl-value">
                        <div class="loading-skeleton" style="width: 120px; height: 36px;"></div>
                    </div>
                    <div class="text-muted">
                        Baseado no snapshot de ontem
                    </div>
                </div>

                <div class="stat-card glass-panel">
                    <div class="stat-title">
                        <span>Status</span>
                        <div style="background: rgba(59, 130, 246, 0.2); padding: 8px; border-radius: 8px; color: var(--secondary);">
                            <i data-lucide="activity" size="20"></i>
                        </div>
                    </div>
                    <div style="display: flex; gap: 24px; margin-top: 8px;">
                        <div>
                            <p class="text-muted" style="font-size: 0.8rem; margin-bottom: 4px;">Carteiras Ativas</p>
                            <p style="font-size: 1.25rem; font-weight: 600;"><?= $wallet_count ?></p>
                        </div>
                        <div>
                            <p class="text-muted" style="font-size: 0.8rem; margin-bottom: 4px;">Último Sync</p>
                            <p style="font-size: 1.1rem; font-weight: 600;"><?= $last_sync ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Dashboard Grid: Chart + Top Holdings -->
            <div class="dashboard-grid animate-fade-in delay-2">
                <!-- Evolution Chart -->
                <div class="glass-panel" style="padding: 24px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
                        <h3 style="font-size: 1.1rem; font-weight: 600;">Evolução Patrimonial</h3>
                        <div style="display: flex; gap: 8px;">
                            <span class="badge badge-purple">7D</span>
                        </div>
                    </div>
                    <div style="position: relative; height: 300px; width: 100%;">
                        <canvas id="portfolioChart"></canvas>
                    </div>
                </div>

                <!-- Top Holdings -->
                <div class="glass-panel" style="padding: 24px;">
                    <h3 style="font-size: 1.1rem; font-weight: 600; margin-bottom: 16px;">Top Holdings</h3>
                    <div id="holdings-list" class="top-holdings-list">
                        <!-- Loading Skeletons -->
                        <div class="holding-item"><div class="loading-skeleton"></div></div>
                        <div class="holding-item"><div class="loading-skeleton"></div></div>
                        <div class="holding-item"><div class="loading-skeleton"></div></div>
                        <div class="holding-item"><div class="loading-skeleton"></div></div>
                    </div>
                    <a href="/main/public/assets.php" style="display: block; text-align: center; margin-top: 16px; color: var(--primary); text-decoration: none; font-size: 0.9rem; font-weight: 500;">Ver todos os ativos →</a>
                </div>
            </div>

            <!-- Recent Transactions Table -->
            <div class="glass-panel animate-fade-in delay-3" style="padding: 24px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h3 style="font-size: 1.1rem; font-weight: 600;">Últimas Transações</h3>
                    <a href="/main/public/transactions.php" style="color: var(--primary); text-decoration: none; font-size: 0.9rem; font-weight: 500;">Ver histórico completo →</a>
                </div>
                
                <div class="table-container">
                    <table class="premium-table">
                        <thead>
                            <tr>
                                <th>Ativo</th>
                                <th>Rede</th>
                                <th>Data</th>
                                <th>Tipo</th>
                                <th style="text-align: right;">Quantidade</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($recent_transactions) > 0): ?>
                                <?php foreach ($recent_transactions as $tx): ?>
                                    <tr>
                                        <td>
                                            <div style="display: flex; align-items: center; gap: 12px;">
                                                <div style="width: 28px; height: 28px; background: rgba(255,255,255,0.1); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.7rem; font-weight: bold;">
                                                    <?= substr(htmlspecialchars($tx['token_symbol'] ?? 'U'), 0, 1) ?>
                                                </div>
                                                <span style="font-weight: 500;"><?= htmlspecialchars($tx['token_symbol'] ?? 'N/A') ?></span>
                                            </div>
                                        </td>
                                        <td><span class="badge badge-<?= htmlspecialchars($tx['network']) ?>"><?= ucfirst($tx['network']) ?></span></td>
                                        <td class="text-muted"><?= date('d/m/Y H:i', $tx['timestamp']) ?></td>
                                        <td><?= ucfirst($tx['transaction_type']) ?></td>
                                        <td style="text-align: right; font-weight: 500; font-family: monospace; font-size: 1rem;">
                                            <?= number_format($tx['value'], 6, ',', '.') ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" style="text-align: center; padding: 48px;">
                                        <i data-lucide="inbox" size="48" style="color: var(--text-muted); margin-bottom: 16px; opacity: 0.5;"></i>
                                        <p class="text-muted">Nenhuma transação sincronizada ainda.</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <!-- Inicializar Ícones -->
    <script>
        lucide.createIcons();
    </script>

    <!-- App Logic (Consumindo a API WP3) -->
    <script>
        document.addEventListener('DOMContentLoaded', async () => {
            try {
                // Fetch dados do WP3
                const response = await fetch('/main/public/api/portfolio-data.php');
                const data = await response.json();
                
                if (data.success) {
                    const snap = data.snapshot;
                    
                    // 1. Atualizar Header Stats
                    const usdFormatter = new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' });
                    const brlFormatter = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });
                    
                    document.getElementById('total-usd').innerHTML = usdFormatter.format(snap.total_value_usd);
                    document.getElementById('total-brl').innerHTML = `≈ ${brlFormatter.format(snap.total_value_brl)}`;
                    
                    // P&L
                    const plElem = document.getElementById('pl-value');
                    if (snap.change_24h_usd !== null && snap.change_24h_usd !== undefined) {
                        const change = snap.change_24h_usd;
                        const pct = snap.change_24h_percent;
                        const isPositive = change >= 0;
                        const colorClass = isPositive ? 'text-green' : 'text-red';
                        const sign = isPositive ? '+' : '';
                        
                        plElem.innerHTML = `<span class="${colorClass}">${sign}${usdFormatter.format(change)} <span style="font-size: 1rem; opacity: 0.8;">(${sign}${pct.toFixed(2)}%)</span></span>`;
                    } else {
                        plElem.innerHTML = '<span class="text-muted">Sem dados 24h</span>';
                    }

                    // 2. Renderizar Top Holdings
                    const holdingsContainer = document.getElementById('holdings-list');
                    if (data.holdings && data.holdings.length > 0) {
                        holdingsContainer.innerHTML = '';
                        data.holdings.slice(0, 4).forEach(h => {
                            const valUsd = usdFormatter.format(h.total_usd);
                            const html = `
                                <div class="holding-item">
                                    <div class="holding-info">
                                        <div class="holding-icon">${h.symbol.charAt(0)}</div>
                                        <div class="holding-details">
                                            <h4>${h.symbol}</h4>
                                            <span>${parseFloat(h.total_amount).toLocaleString(undefined, {maximumFractionDigits:4})} tokens</span>
                                        </div>
                                    </div>
                                    <div class="holding-value">
                                        <h4>${valUsd}</h4>
                                        <span class="${h.pnl_pct >= 0 ? 'text-green' : 'text-red'}">${h.pnl_pct >= 0 ? '+' : ''}${h.pnl_pct ? h.pnl_pct.toFixed(2) : '0.00'}%</span>
                                    </div>
                                </div>
                            `;
                            holdingsContainer.insertAdjacentHTML('beforeend', html);
                        });
                    } else {
                        holdingsContainer.innerHTML = '<p class="text-muted" style="padding: 20px 0;">Nenhum ativo encontrado.</p>';
                    }

                    // 3. Renderizar Gráfico Chart.js
                    if (data.history && data.history.length > 0) {
                        const ctx = document.getElementById('portfolioChart').getContext('2d');
                        
                        // Preparar gradiente para a linha
                        const gradient = ctx.createLinearGradient(0, 0, 0, 400);
                        gradient.addColorStop(0, 'rgba(110, 86, 207, 0.5)');   
                        gradient.addColorStop(1, 'rgba(110, 86, 207, 0.0)');

                        const labels = data.history.map(item => item.date.split('-').reverse().slice(0, 2).join('/')); // DD/MM
                        const values = data.history.map(item => parseFloat(item.total_value_usd));

                        new Chart(ctx, {
                            type: 'line',
                            data: {
                                labels: labels,
                                datasets: [{
                                    label: 'Patrimônio (USD)',
                                    data: values,
                                    borderColor: '#A78BFA',
                                    borderWidth: 3,
                                    backgroundColor: gradient,
                                    fill: true,
                                    pointBackgroundColor: '#6E56CF',
                                    pointBorderColor: '#FFF',
                                    pointBorderWidth: 2,
                                    pointRadius: 4,
                                    pointHoverRadius: 6,
                                    tension: 0.4 // Curva suave
                                }]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                plugins: {
                                    legend: { display: false },
                                    tooltip: {
                                        mode: 'index',
                                        intersect: false,
                                        backgroundColor: 'rgba(18, 24, 38, 0.9)',
                                        titleColor: '#fff',
                                        bodyColor: '#A78BFA',
                                        borderColor: 'rgba(255,255,255,0.1)',
                                        borderWidth: 1,
                                        padding: 12,
                                        callbacks: {
                                            label: function(context) {
                                                return ' ' + usdFormatter.format(context.parsed.y);
                                            }
                                        }
                                    }
                                },
                                scales: {
                                    x: {
                                        grid: { color: 'rgba(255, 255, 255, 0.05)', drawBorder: false },
                                        ticks: { color: '#94A3B8' }
                                    },
                                    y: {
                                        grid: { color: 'rgba(255, 255, 255, 0.05)', drawBorder: false },
                                        ticks: { 
                                            color: '#94A3B8',
                                            callback: function(value) { return '$' + value.toLocaleString(); }
                                        }
                                    }
                                },
                                interaction: {
                                    mode: 'nearest',
                                    axis: 'x',
                                    intersect: false
                                }
                            }
                        });
                    } else {
                        document.getElementById('portfolioChart').parentElement.innerHTML = 
                            '<div style="height: 100%; display: flex; align-items: center; justify-content: center; color: var(--text-muted);">Dados históricos insuficientes para gerar o gráfico.</div>';
                    }
                }
            } catch (err) {
                console.error("Erro ao buscar dados do WP3:", err);
            }
        });
    </script>
</body>
</html>
