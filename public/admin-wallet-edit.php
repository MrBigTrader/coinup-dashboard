<?php
/**
 * Admin Wallet Edit - CoinUp
 * Editar carteira de um cliente
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/auth.php';
require_once dirname(__DIR__) . '/config/middleware.php';

Middleware::requireAuth();
Middleware::requireAdmin();

$auth = Auth::getInstance();
$user = $auth->getCurrentUser();

$id = $_GET['id'] ?? 0;
$success = '';
$error = '';

try {
    $db = Database::getInstance()->getConnection();

    // Buscar dados da carteira
    $stmt = $db->prepare("
        SELECT w.*, u.id as user_id, u.name as user_name, u.email as user_email
        FROM wallets w
        JOIN users u ON w.user_id = u.id
        WHERE w.id = ?
    ");
    $stmt->execute([$id]);
    $wallet = $stmt->fetch();

    if (!$wallet) {
        header('Location: /main/public/admin-wallets.php');
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $label = trim($_POST['label'] ?? '');
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        try {
            $stmt = $db->prepare("
                UPDATE wallets SET label = ?, is_active = ? WHERE id = ?
            ");
            $stmt->execute([$label ?: null, $is_active, $id]);

            header('Location: /main/public/admin-wallets.php?success=Carteira atualizada');
            exit;
        } catch (Exception $e) {
            $error = 'Erro ao atualizar: ' . $e->getMessage();
        }
    }
} catch (Exception $e) {
    error_log("Erro em admin-wallet-edit: " . $e->getMessage());
    header('Location: /main/public/admin-wallets.php');
    exit;
}

$network_labels = [
    'ethereum' => 'Ethereum',
    'bnb' => 'BNB Chain',
    'arbitrum' => 'Arbitrum',
    'base' => 'Base',
    'polygon' => 'Polygon'
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Carteira - Admin - CoinUp</title>
    <link rel="stylesheet" href="/main/assets/css/style.css">
</head>
<body>
    <div class="dashboard-container" style="display: flex; min-height: 100vh;">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="logo">
                <h1>🪙 CoinUp</h1>
            </div>

            <ul class="nav-menu">
                <li><a href="/main/public/admin.php"><span>👥 Clientes</span></a></li>
                <li><a href="/main/public/admin-wallets.php" class="active"><span>🔗 Carteiras</span></a></li>
                <li><a href="/main/public/admin-sync.php"><span>🔄 Sincronização</span></a></li>
                <li><a href="/main/public/admin-logs.php"><span>📋 Logs</span></a></li>
            </ul>

            <div class="user-info">
                <p><strong><?= htmlspecialchars($user['name']) ?></strong></p>
                <small>Administrador</small>
                <a href="/main/public/logout.php" style="margin-top: 10px; padding: 8px 16px; background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.3); color: #fca5a5; border-radius: 8px; cursor: pointer; width: 100%; text-decoration: none; display: block; text-align: center;">Sair</a>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <div class="header">
                <div>
                    <h2>Editar Carteira</h2>
                    <p>Cliente: <?= htmlspecialchars($wallet['user_name']) ?></p>
                </div>
                <a href="/main/public/admin-wallets.php" class="btn btn-secondary">← Voltar</a>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error">❌ <?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <div class="card" style="max-width: 600px;">
                <div style="margin-bottom: 20px; padding: 15px; background: rgba(255,255,255,0.05); border-radius: 10px;">
                    <p><strong>Rede:</strong> <?= $network_labels[$wallet['network']] ?? ucfirst($wallet['network']) ?></p>
                    <p><strong>Endereço:</strong> <code style="color: #a855f7;"><?= $wallet['address'] ?></code></p>
                </div>

                <form method="POST">
                    <div class="form-group">
                        <label class="form-label" for="label">Label</label>
                        <input
                            type="text"
                            id="label"
                            name="label"
                            class="form-input"
                            value="<?= htmlspecialchars($wallet['label'] ?? '') ?>"
                            placeholder="Ex: Carteira principal"
                        >
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            <input type="checkbox" name="is_active" value="1" <?= $wallet['is_active'] ? 'checked' : '' ?>>
                            Carteira Ativa
                        </label>
                    </div>

                    <div style="display: flex; gap: 16px; margin-top: 24px;">
                        <button type="submit" class="btn btn-primary">Salvar Alterações</button>
                        <a href="/main/public/admin-wallets.php" class="btn btn-secondary">Cancelar</a>
                    </div>
                </form>
            </div>
        </main>
    </div>
</body>
</html>
