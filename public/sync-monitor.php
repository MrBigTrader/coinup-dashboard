<?php
/**
 * Sync Monitor - Verificador de Status do Cron Job e Progresso
 * Revisão: 2026-04-07-LocalFirst
 * 
 * USO: Via browser (apenas admin)
 * URL: https://coinup.com.br/main/public/sync-monitor.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/auth.php';

if (session_status() === PHP_SESSION_NONE) {
    session_name('COINUPSESS');
    session_start();
}

// Permitir acesso apenas a admins ou via CLI (para testes internos)
$is_cli = php_sapi_name() === 'cli';
if (!$is_cli && (!isset($_SESSION['logged_in']) || $_SESSION['user_role'] !== 'admin')) {
    die('Acesso negado.');
}

$db = Database::getInstance()->getConnection();

// 1. Verificar último log do Cron Job
$stmt = $db->query("SELECT executed_at, status, transactions_found, blocks_processed FROM sync_logs ORDER BY executed_at DESC LIMIT 1");
$lastLog = $stmt->fetch();

// 2. Verificar estado atual das wallets
$stmt = $db->query("
    SELECT w.id, w.network, ss.last_block_synced, sb.current_block, 
           (sb.current_block - ss.last_block_synced) as blocks_remaining
    FROM wallets w
    JOIN sync_state ss ON w.id = ss.wallet_id AND w.network = ss.network
    CROSS JOIN (SELECT MAX(block_number) as current_block FROM transactions_cache UNION ALL SELECT 90000000) sb
    WHERE w.is_active = 1
");
$wallets = $stmt->fetchAll();

// Estimativas de blocos atuais por rede (Valores aproximados de Abril 2026)
$networkCurrentBlocks = [
    'ethereum' => 22000000,
    'bnb'      => 90000000,
    'arbitrum' => 290000000,
    'base'     => 25000000,
    'polygon'  => 60000000,
];

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monitor de Sincronização - CoinUp</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #1a1a2e; color: #eee; padding: 20px; }
        .container { max-width: 800px; margin: 0 auto; }
        .card { background: rgba(30, 41, 59, 0.8); border-radius: 10px; padding: 20px; margin-bottom: 20px; border: 1px solid #334155; }
        h1 { color: #fff; }
        h2 { color: #a78bfa; margin-top: 0; }
        .status-ok { color: #4ade80; font-weight: bold; }
        .status-warn { color: #fbbf24; font-weight: bold; }
        .status-err { color: #f87171; font-weight: bold; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #334155; }
        th { color: #94a3b8; }
        .progress-bar { background: #334155; height: 20px; border-radius: 10px; overflow: hidden; margin-top: 5px; }
        .progress-fill { background: linear-gradient(90deg, #8b5cf6, #3b82f6); height: 100%; transition: width 0.5s; }
    </style>
</head>
<body>
    <div class="container">
        <h1>📊 Monitor de Sincronização (WP2)</h1>
        
        <!-- Status do Cron Job -->
        <div class="card">
            <h2>⏰ Status do Cron Job</h2>
            <?php if ($lastLog): ?>
                <?php 
                    $lastExec = strtotime($lastLog['executed_at']);
                    $now = time();
                    $diff = $now - $lastExec;
                    $isRunning = $diff < 3600; // Menos que 60 min (Cron Job pode ter atraso)
                ?>
                <p><strong>Última Execução:</strong> <?= date('d/m/Y H:i:s', $lastExec) ?></p>
                <p><strong>Status:</strong> 
                    <?php if ($isRunning): ?>
                        <span class="status-ok">✅ ATIVO (Rodando há menos de 30min)</span>
                    <?php else: ?>
                        <span class="status-err">❌ INATIVO (Parado há <?= round($diff/60) ?> min)</span>
                    <?php endif; ?>
                </p>
                <p><strong>Último Resultado:</strong> <?= $lastLog['status'] ?> | Blocos: <?= number_format($lastLog['blocks_processed']) ?> | Tx: <?= $lastLog['transactions_found'] ?></p>
            <?php else: ?>
                <p class="status-err">⚠️ Nenhum log encontrado. O Cron Job pode não estar configurado ou ainda não rodou.</p>
            <?php endif; ?>
        </div>

        <!-- Progresso por Wallet -->
        <div class="card">
            <h2>🔄 Progresso da Sincronização Histórica</h2>
            <table>
                <thead>
                    <tr>
                        <th>Wallet ID</th>
                        <th>Rede</th>
                        <th>Bloco Atual</th>
                        <th>Progresso</th>
                        <th>Restante</th>
                        <th>Estimativa (ETA)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($wallets as $w): ?>
                        <?php 
                            $targetBlock = $networkCurrentBlocks[$w['network']] ?? 90000000;
                            $progress = min(100, ($w['last_block_synced'] / $targetBlock) * 100);
                            $remainingBlocks = max(0, $targetBlock - $w['last_block_synced']);
                            
                            // Estimativa: 500k blocos a cada 30 min (modo turbo) ou 50k quando encontra tx
                            $cyclesNeeded = ceil($remainingBlocks / 500000);
                            $minutesEta = $cyclesNeeded * 30;
                            $hoursEta = round($minutesEta / 60, 1);
                            
                            // Se for menos de 1 hora, mostrar em minutos
                            $etaDisplay = $hoursEta < 1 ? round($minutesEta) . ' min' : $hoursEta . ' horas';
                        ?>
                        <tr>
                            <td>#<?= $w['id'] ?></td>
                            <td><?= strtoupper($w['network']) ?></td>
                            <td><?= number_format($w['last_block_synced']) ?> / <?= number_format($targetBlock) ?></td>
                            <td style="width: 30%;">
                                <div class="progress-bar">
                                    <div class="progress-fill" style="width: <?= $progress ?>%"></div>
                                </div>
                                <small><?= round($progress, 2) ?>%</small>
                            </td>
                            <td><?= number_format($remainingBlocks) ?></td>
                            <td><?= $etaDisplay ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <p style="font-size: 12px; color: #94a3b8; margin-top: 10px;">
                * Estimativa baseada no "Modo Turbo" (500.000 blocos/ciclo quando vazio). Pode variar.
            </p>
        </div>

        <div style="text-align: center; margin-top: 20px;">
            <button onclick="location.reload()" style="padding: 10px 20px; cursor: pointer;">🔄 Atualizar Página</button>
        </div>
    </div>
</body>
</html>
