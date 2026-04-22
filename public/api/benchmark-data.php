<?php
/**
 * API: Benchmark Data
 * GET /main/public/api/benchmark-data.php
 * 
 * Returns JSON with:
 * - Current benchmark values
 * - Historical benchmark series (for Chart.js)
 * - Portfolio vs benchmark comparison
 * 
 * Query params:
 * - period: '7d', '30d', '90d', '1y', 'all' (default: '30d')
 * - currency: 'USD', 'BRL', null for both (default: null)
 * - section: 'all', 'current', 'history', 'comparison' (default: 'all')
 */

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');

$base_path = dirname(dirname(__DIR__));

require_once $base_path . '/config/database.php';
require_once $base_path . '/config/auth.php';
require_once $base_path . '/config/middleware.php';
require_once $base_path . '/src/Services/PortfolioService.php';

Middleware::requireAuth();

$userId = $_SESSION['user_id'];
$period = $_GET['period'] ?? '30d';
$currency = $_GET['currency'] ?? null;
$section = $_GET['section'] ?? 'all';

// Admin pode ver dados de outro cliente
if ($_SESSION['user_role'] === 'admin' && !empty($_GET['client_id'])) {
    $userId = (int)$_GET['client_id'];
}

// Validar
$validPeriods = ['7d', '30d', '90d', '1y', 'all'];
if (!in_array($period, $validPeriods)) $period = '30d';
if ($currency && !in_array($currency, ['USD', 'BRL'])) $currency = null;

try {
    $db = Database::getInstance()->getConnection();
    $portfolio = new PortfolioService($db);

    $response = ['success' => true];

    // Benchmarks atuais
    if ($section === 'all' || $section === 'current') {
        $stmt = $db->query("
            SELECT symbol, name, value, change_24h, currency, source, 
                   last_updated
            FROM benchmarks 
            WHERE value > 0
            ORDER BY currency, symbol
        ");
        $benchmarks = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Separar por moeda
        $response['current'] = [
            'USD' => array_values(array_filter($benchmarks, fn($b) => $b['currency'] === 'USD')),
            'BRL' => array_values(array_filter($benchmarks, fn($b) => $b['currency'] === 'BRL')),
        ];
    }

    // Série histórica
    if ($section === 'all' || $section === 'history') {
        $response['history'] = $portfolio->getBenchmarkHistory($period, $currency);
    }

    // Comparativo portfólio vs benchmarks
    if ($section === 'all' || $section === 'comparison') {
        $response['comparison'] = $portfolio->getBenchmarkComparison($userId);
    }

    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    error_log("Benchmark API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro interno ao carregar dados de benchmarks.'
    ]);
}
