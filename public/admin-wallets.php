<?php
/**
 * Admin Wallets - Listagem de Carteiras
 * Revisão: 2026-04-09-Fix
 * Descrição: Listagem de carteiras para o administrador.
 */
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/auth.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

$db = Database::getInstance()->getConnection();
$stmt = $db->query("
    SELECT w.*, u.name as user_name 
    FROM wallets w 
    JOIN users u ON w.user_id = u.id 
    ORDER BY w.created_at DESC
");
$wallets = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Carteiras - CoinUp Admin</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #1a1a2e; color: #fff; }
        .header { padding: 20px; background: #16213e; display: flex; justify-content: space-between; align-items: center; }
        .container { padding: 20px; }
        table { width: 100%; border-collapse: collapse; background: #0f3460; border-radius: 8px; overflow: hidden; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #1a1a2e; }
        th { background: #533483; }
        .status-active { color: #4ade80; font-weight: bold; }
        .status-inactive { color: #f87171; font-weight: bold; }
        .btn { padding: 5px 10px; background: #8b5cf6; color: white; text-decoration: none; border-radius: 4px; font-size: 12px; display: inline-block; }
        .btn:hover { opacity: 0.8; }
    </style>
</head>
<body>
    <div class="header">
        <h1>🪙 CoinUp Admin</h1>
        <a href="admin.php" class="btn">Voltar ao Painel</a>
    </div>

    <div class="container">
        <h2>🔗 Carteiras Cadastradas</h2>
        
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Cliente</th>
                    <th>Rede</th>
                    <th>Endereço</th>
                    <th>Status</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($wallets as $w): ?>
                    <tr>
                        <td><?= $w['id'] ?></td>
                        <td><?= htmlspecialchars($w['user_name']) ?></td>
                        <td><?= strtoupper($w['network']) ?></td>
                        <td style="font-family: monospace; font-size: 12px;"><?= substr($w['address'], 0, 10) ?>...</td>
                        <td class="<?= $w['is_active'] ? 'status-active' : 'status-inactive' ?>">
                            <?= $w['is_active'] ? 'Ativa' : 'Inativa' ?>
                        </td>
                        <td>
                            <a href="admin-wallet-edit.php?id=<?= $w['id'] ?>" class="btn">Editar</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                
                <?php if (empty($wallets)): ?>
                    <tr><td colspan="6" style="text-align: center; padding: 20px;">Nenhuma carteira encontrada.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</body>
</html>