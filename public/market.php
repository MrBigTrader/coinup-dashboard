<?php
/**
 * Market - Tela de Mercado e Benchmarks (V2)
 * Revisão: 2026-04-09-Fix-Access
 * Descrição: Exibe benchmarks. Permite acesso Admin e Client.
 */
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/auth.php';
require_once dirname(__DIR__) . '/config/middleware.php';

Middleware::requireAuth();

$userName = $_SESSION['user_name'] ?? 'Usuário';
$userRole = $_SESSION['user_role'] ?? 'client';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mercado - CoinUp</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #1a1a2e; color: #fff; margin: 0; }
        .header { padding: 15px 20px; background: #16213e; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #334155; }
        .container { max-width: 1200px; margin: 0 auto; padding: 20px; }
        .card { background: rgba(255,255,255,0.05); padding: 20px; border-radius: 10px; margin-bottom: 20px; border: 1px solid #334155; }
        h2 { color: #8b5cf6; margin-top: 0; }
        .benchmarks { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; }
        .bench-card { background: #0f3460; padding: 20px; border-radius: 8px; text-align: center; border: 1px solid #1e3a8a; }
        .bench-val { font-size: 24px; font-weight: bold; color: #4ade80; margin: 10px 0; }
        .bench-card h4 { margin: 0; color: #a78bfa; font-size: 14px; text-transform: uppercase; }
        .role-badge { background: #334155; padding: 4px 8px; border-radius: 4px; font-size: 12px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>🪙 CoinUp - Mercado</h1>
        <div>
            <span class="role-badge"><?= strtoupper($userRole) ?></span>
            Bem-vindo, <?= htmlspecialchars($userName) ?> | 
            <a href="logout.php" style="color: #f87171;">Sair</a>
        </div>
    </div>

    <div class="container">
        <h2>📈 Visão de Mercado e Benchmarks</h2>
        <p>Comparativo de rentabilidade do seu portfólio vs. indicadores globais (Dados reais em breve via WP3).</p>

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
            </div>
        </div>
    </div>
</body>
</html>