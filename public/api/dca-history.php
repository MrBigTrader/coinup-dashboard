<?php
/**
 * API: DCA History Data (para gráfico DCA)
 * GET /main/public/api/dca-history.php
 * 
 * Returns JSON with DCA evolution for a specific token:
 * - Average price over time
 * - Price history for overlay chart
 * 
 * Query params:
 * - token: token symbol (required, e.g. 'ETH', 'BTC')
 */

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');

$base_path = dirname(dirname(__DIR__));

require_once $base_path . '/config/database.php';
require_once $base_path . '/config/auth.php';
require_once $base_path . '/config/middleware.php';
require_once $base_path . '/src/Services/DCAService.php';

Middleware::requireAuth();

$userId = $_SESSION['user_id'];
$tokenSymbol = strtoupper(trim($_GET['token'] ?? ''));

// Admin pode ver dados de outro cliente
if ($_SESSION['user_role'] === 'admin' && !empty($_GET['client_id'])) {
    $userId = (int)$_GET['client_id'];
}

if (empty($tokenSymbol)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Parâmetro "token" é obrigatório']);
    exit;
}

try {
    $db = Database::getInstance()->getConnection();
    $dca = new DCAService($db);

    // DCA history (preço médio ao longo do tempo)
    $dcaHistory = $dca->getDCAHistory($userId, $tokenSymbol);

    // Price history (preço de mercado ao longo do tempo)
    $stmt = $db->prepare("
        SELECT 
            DATE(recorded_at) as date,
            AVG(price_usd) as price_usd
        FROM price_history
        WHERE token_symbol = ?
        GROUP BY DATE(recorded_at)
        ORDER BY date ASC
    ");
    $stmt->execute([$tokenSymbol]);
    $priceHistory = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Preço atual
    $priceStmt = $db->prepare("SELECT price_usd, price_brl, change_24h FROM token_prices WHERE token_symbol = ?");
    $priceStmt->execute([$tokenSymbol]);
    $currentPrice = $priceStmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'token' => $tokenSymbol,
        'dca_history' => $dcaHistory,
        'price_history' => $priceHistory,
        'current_price' => $currentPrice,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    error_log("DCA History API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro interno ao carregar histórico DCA.'
    ]);
}
