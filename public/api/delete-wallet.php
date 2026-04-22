<?php
/**
 * API: Remover Carteira
 * POST /main/public/api/delete-wallet.php
 */

header('Content-Type: application/json');

$base_path = dirname(dirname(__DIR__));

require_once $base_path . '/config/database.php';
require_once $base_path . '/config/auth.php';
require_once $base_path . '/config/middleware.php';

Middleware::requireAuth();

$auth = Auth::getInstance();

// Verificar que é POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

// Verificar CSRF
if (!$auth->verifyCsrfToken($input['csrf_token'] ?? null)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Token de segurança inválido. Recarregue a página.']);
    exit;
}

$wallet_id = $input['wallet_id'] ?? 0;
$user_id = $_SESSION['user_id'];

if (!$wallet_id) {
    echo json_encode(['success' => false, 'message' => 'ID da carteira obrigatório']);
    exit;
}

try {
    $db = Database::getInstance()->getConnection();
    
    $stmt = $db->prepare("SELECT id FROM wallets WHERE id = ? AND user_id = ?");
    $stmt->execute([$wallet_id, $user_id]);
    
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Carteira não encontrada']);
        exit;
    }
    
    $stmt = $db->prepare("DELETE FROM wallets WHERE id = ?");
    $stmt->execute([$wallet_id]);
    
    echo json_encode(['success' => true, 'message' => 'Carteira removida com sucesso']);
    
} catch (Exception $e) {
    error_log("Erro ao remover carteira: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro interno. Tente novamente.']);
}
?>
