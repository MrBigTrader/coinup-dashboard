<?php
/**
 * API: Portfolio Data
 * GET /main/public/api/portfolio-data.php
 * 
 * Returns JSON with:
 * - Portfolio snapshot (total value, 24h change, top holdings, networks)
 * - Portfolio evolution history (for Chart.js line chart)
 * - P&L summary (DCA-based)
 * - Recent activity
 * 
 * Query params:
 * - period: '7d', '30d', '90d', '1y', 'all' (default: '30d')
 * - section: 'all', 'snapshot', 'history', 'pnl', 'holdings', 'activity' (default: 'all')
 */

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');

$base_path = dirname(dirname(__DIR__));

require_once $base_path . '/config/database.php';
require_once $base_path . '/config/auth.php';
require_once $base_path . '/config/middleware.php';
require_once $base_path . '/src/Services/PortfolioService.php';
require_once $base_path . '/src/Services/DCAService.php';

Middleware::requireAuth();

$userId = $_SESSION['user_id'];
$period = $_GET['period'] ?? '30d';
$section = $_GET['section'] ?? 'all';

// Admin pode ver dados de outro cliente
if ($_SESSION['user_role'] === 'admin' && !empty($_GET['client_id'])) {
    $userId = (int)$_GET['client_id'];
}

// Validar period
$validPeriods = ['7d', '30d', '90d', '1y', 'all'];
if (!in_array($period, $validPeriods)) {
    $period = '30d';
}

try {
    $db = Database::getInstance()->getConnection();
    $portfolio = new PortfolioService($db);
    $dca = new DCAService($db);

    $response = ['success' => true];

    // Snapshot (sempre incluído)
    if ($section === 'all' || $section === 'snapshot') {
        $response['snapshot'] = $portfolio->getPortfolioSnapshot($userId);
    }

    // Evolução patrimonial (Chart.js)
    if ($section === 'all' || $section === 'history') {
        $history = $portfolio->getPortfolioHistory($userId, $period);
        
        // Injetar valor em tempo real de hoje
        $currentValueUsd = $portfolio->getTotalValueUsd($userId);
        $currentValueBrl = $portfolio->getTotalValueBrl($userId);
        $today = date('Y-m-d');
        
        if (empty($history) || end($history)['date'] !== $today) {
            $history[] = [
                'date' => $today,
                'total_value_usd' => $currentValueUsd,
                'total_value_brl' => $currentValueBrl
            ];
        } else {
            $lastIndex = count($history) - 1;
            $history[$lastIndex]['total_value_usd'] = $currentValueUsd;
            $history[$lastIndex]['total_value_brl'] = $currentValueBrl;
        }
        
        $response['history'] = $history;
    }

    // P&L e DCA
    if ($section === 'all' || $section === 'pnl') {
        $response['pnl_summary'] = $dca->getPortfolioPnLSummary($userId);
    }

    // Holdings detalhados com DCA
    if ($section === 'all' || $section === 'holdings') {
        $response['holdings'] = $dca->calculateForUser($userId);
    }

    // Atividade recente
    if ($section === 'all' || $section === 'activity') {
        $response['recent_activity'] = $portfolio->getRecentActivity($userId, 10);
    }

    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    error_log("Portfolio API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro interno ao carregar dados do portfólio.'
    ]);
}
