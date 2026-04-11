<?php
/**
 * Market - Tela de Mercado e Benchmarks
 * Revisão: 2026-04-11-Sidebar
 * Descrição: Exibe benchmarks com layout completo (sidebar + conteúdo).
 */
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/auth.php';
require_once dirname(__DIR__) . '/config/middleware.php';

Middleware::requireAuth();

$auth = Auth::getInstance();
$user = $auth->getCurrentUser();
$userRole = $_SESSION['user_role'] ?? 'client';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mercado - CoinUp</title>
    <link rel="stylesheet" href="/main/assets/css/style.css">
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
            margin-top: auto;
        }

        .user-info strong {
            color: #fff;
            display: block;
            margin-bottom: 4px;
        }

        .user-info small {
            color: #94a3b8;
        }

        .btn-logout {
            display: inline-block;
            margin-top: 10px;
            padding: 6px 12px;
            background: rgba(239, 68, 68, 0.2);
            color: #f87171;
            text-decoration: none;
            border-radius: 6px;
            font-size: 0.85rem;
        }

        /* Main Content */
        .main-content {
            flex: 1;
            padding: 30px;
            overflow-y: auto;
        }

        .header {
            margin-bottom: 30px;
        }

        .header h2 {
            font-size: 1.8rem;
            color: #fff;
            margin-bottom: 5px;
        }

        .header p {
            color: #94a3b8;
        }

        .card {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border-radius: 12px;
            padding: 25px;
            border: 1px solid rgba(255, 255, 255, 0.08);
        }

        .card h3 {
            color: #a855f7;
            margin-bottom: 15px;
        }

        .benchmarks {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }

        .bench-card {
            background: rgba(15, 52, 96, 0.6);
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            border: 1px solid rgba(30, 58, 138, 0.5);
        }

        .bench-card h4 {
            color: #a78bfa;
            font-size: 0.85rem;
            text-transform: uppercase;
            margin-bottom: 10px;
        }

        .bench-val {
            font-size: 1.5rem;
            font-weight: bold;
            color: #4ade80;
            margin: 8px 0;
        }

        .bench-card small {
            color: #64748b;
            font-size: 0.75rem;
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
                <li><a href="/main/public/dashboard.php"><span>📊 Overview</span></a></li>
                <li><a href="/main/public/my-wallets.php"><span>🔗 Minhas Carteiras</span></a></li>
                <li><a href="/main/public/assets.php"><span>💼 Assets</span></a></li>
                <li><a href="/main/public/transactions.php"><span>📝 Transactions</span></a></li>
                <li><a href="/main/public/market.php" class="active"><span>📈 Market</span></a></li>
            </ul>

            <div class="user-info">
                <strong><?= htmlspecialchars($user['name']) ?></strong>
                <small><?= htmlspecialchars($user['email']) ?></small>
                <a href="/main/public/logout.php" class="btn-logout">Sair</a>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <div class="header">
                <h2>📊 Visão de Mercado e Benchmarks</h2>
                <p>Comparativo de rentabilidade do seu portfólio vs. indicadores globais (Dados reais em breve via WP3).</p>
            </div>

            <div class="card">
                <h3>Indicadores Globais (Status: Aguardando WP3)</h3>
                <div class="benchmarks">
                    <div class="bench-card">
                        <h4>Bitcoin (BTC)</h4>
                        <div class="bench-val">--</div>
                        <small>Fonte: CoinGecko</small>
                    </div>
                    <div class="bench-card">
                        <h4>S&P 500</h4>
                        <div class="bench-val">--</div>
                        <small>Fonte: Alpha Vantage</small>
                    </div>
                    <div class="bench-card">
                        <h4>Ouro (XAU)</h4>
                        <div class="bench-val">--</div>
                        <small>Fonte: Alpha Vantage</small>
                    </div>
                    <div class="bench-card">
                        <h4>CDI (Acumulado)</h4>
                        <div class="bench-val">--</div>
                        <small>Fonte: BCB</small>
                    </div>
                    <div class="bench-card">
                        <h4>IBOVESPA</h4>
                        <div class="bench-val">--</div>
                        <small>Fonte: Yahoo Finance</small>
                    </div>
                    <div class="bench-card">
                        <h4>T-Bills (10Y)</h4>
                        <div class="bench-val">--</div>
                        <small>Fonte: Alpha Vantage</small>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
