<?php
/**
 * API: Adicionar Carteira
 * POST /main/public/api/add-wallet.php
 */

header('Content-Type: application/json');

// Configurar caminhos de forma compatível com PHP 5.6+
$base_path = dirname(dirname(__DIR__));

require_once $base_path . '/config/database.php';
require_once $base_path . '/config/auth.php';

// Verificar autenticação
if (session_status() === PHP_SESSION_NONE) {
    session_name('COINUPSESS');
    session_start();
}

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Não autenticado']);
    exit;
}

if ($_SESSION['user_role'] !== 'client') {
    echo json_encode(['success' => false, 'message' => 'Acesso negado']);
    exit;
}

// Receber dados
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(['success' => false, 'message' => 'Dados inválidos']);
    exit;
}

$address = trim($input['address'] ?? '');
$network = trim($input['network'] ?? '');
$label = trim($input['label'] ?? '');

// Validações
if (empty($address) || empty($network)) {
    echo json_encode(['success' => false, 'message' => 'Endereço e rede são obrigatórios']);
    exit;
}

// Validar formato do endereço EVM
if (!preg_match('/^0x[a-fA-F0-9]{40}$/', $address)) {
    echo json_encode(['success' => false, 'message' => 'Endereço EVM inválido']);
    exit;
}

// Validar rede
$valid_networks = ['ethereum', 'bnb', 'arbitrum', 'base', 'polygon'];
if (!in_array($network, $valid_networks)) {
    echo json_encode(['success' => false, 'message' => 'Rede inválida']);
    exit;
}

try {
    $db = Database::getInstance()->getConnection();
    $user_id = $_SESSION['user_id'];
    
    // Converter endereço para lowercase para consistência
    $address = strtolower($address);
    
    // Verificar se carteira já existe
    $stmt = $db->prepare("SELECT id FROM wallets WHERE user_id = ? AND network = ? AND address = ?");
    $stmt->execute([$user_id, $network, $address]);
    
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Esta carteira já está cadastrada']);
        exit;
    }
    
    // Inserir carteira
    $stmt = $db->prepare("
        INSERT INTO wallets (user_id, network, address, label, is_active)
        VALUES (?, ?, ?, ?, 1)
    ");
    $stmt->execute([$user_id, $network, $address, $label ?: null]);
    
    $wallet_id = $db->lastInsertId();
    
    // Criar estado de sincronização inicial
    $stmt = $db->prepare("
        INSERT INTO sync_state (wallet_id, network, last_block_synced, sync_status)
        VALUES (?, ?, 0, 'idle')
    ");
    $stmt->execute([$wallet_id, $network]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Carteira adicionada com sucesso',
        'wallet_id' => $wallet_id
    ]);
    
} catch (Exception $e) {
    error_log("Erro ao adicionar carteira: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro: ' . $e->getMessage()]);
}
?>
