<?php
/**
 * API: Ativar/Desativar Carteira
 * POST /main/public/api/toggle-wallet.php
 */

header('Content-Type: application/json');

$base_path = dirname(dirname(__DIR__));

require_once $base_path . '/config/database.php';
require_once $base_path . '/config/auth.php';

if (session_status() === PHP_SESSION_NONE) {
    session_name('COINUPSESS');
    session_start();
}

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['user_role'] !== 'client') {
    echo json_encode(['success' => false, 'message' => 'Não autenticado']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$wallet_id = $input['wallet_id'] ?? 0;
$is_active = $input['is_active'] ?? 1;
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

    $stmt = $db->prepare("UPDATE wallets SET is_active = ? WHERE id = ?");
    $stmt->execute([$is_active ? 1 : 0, $wallet_id]);

    echo json_encode(['success' => true, 'message' => 'Status atualizado']);

} catch (Exception $e) {
    error_log("Erro ao atualizar carteira: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro: ' . $e->getMessage()]);
}
?>
