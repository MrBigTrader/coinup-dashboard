<?php
/**
 * Admin Client Edit - CoinUp
 * Editar cliente
 */

require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/auth.php';
require_once dirname(__DIR__) . '/config/middleware.php';

Middleware::requireAuth();
Middleware::requireAdmin();

$auth = Auth::getInstance();
$user = $auth->getCurrentUser();

$db = Database::getInstance()->getConnection();

$id = $_GET['id'] ?? 0;
$success = '';
$error = '';

// Buscar cliente
$stmt = $db->prepare("SELECT id, name, email, status FROM users WHERE id = ? AND role = 'client'");
$stmt->execute([$id]);
$client = $stmt->fetch();

if (!$client) {
    header('Location: /main/public/admin.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $status = $_POST['status'] ?? 'active';
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Validações
    if (empty($name) || empty($email)) {
        $error = 'Preencha todos os campos obrigatórios.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'E-mail inválido.';
    } else {
        // Verificar se e-mail já existe para outro usuário
        $stmt = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$email, $id]);
        if ($stmt->fetch()) {
            $error = 'Este e-mail já está cadastrado para outro usuário.';
        } else {
            try {
                // Atualizar dados
                if (!empty($password)) {
                    if (strlen($password) < 8) {
                        $error = 'A senha deve ter pelo menos 8 caracteres.';
                    } elseif ($password !== $confirm_password) {
                        $error = 'As senhas não coincidem.';
                    } else {
                        $password_hash = password_hash($password, PASSWORD_DEFAULT);
                        $stmt = $db->prepare("
                            UPDATE users
                            SET name = ?, email = ?, password_hash = ?, status = ?
                            WHERE id = ?
                        ");
                        $stmt->execute([$name, $email, $password_hash, $status, $id]);
                    }
                } else {
                    $stmt = $db->prepare("
                        UPDATE users
                        SET name = ?, email = ?, status = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([$name, $email, $status, $id]);
                }

                if (empty($error)) {
                    $success = 'Cliente atualizado com sucesso!';
                    header('Location: /main/public/admin.php?success=client_updated');
                    exit;
                }
            } catch (Exception $e) {
                error_log("Erro ao atualizar cliente: " . $e->getMessage());
                $error = 'Erro ao atualizar cliente. Tente novamente.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Cliente - Admin - CoinUp</title>
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
                <li><a href="/main/public/admin-wallets.php"><span>🔗 Carteiras</span></a></li>
                <li><a href="/main/public/admin-sync.php"><span>🔄 Sincronização</span></a></li>
                <li><a href="/main/public/sync-manual.php"><span>🔧 Sync Manual</span></a></li>
                <li><a href="/main/public/admin-logs.php"><span>📋 Logs</span></a></li>
            </ul>

            <div class="user-info">
                <p><strong><?= htmlspecialchars($user['name']) ?></strong></p>
                <small>Administrador</small>
                <a href="/main/public/logout.php" class="btn-logout" style="margin-top: 10px; padding: 8px 16px; background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.3); color: #fca5a5; border-radius: 8px; cursor: pointer; width: 100%; text-decoration: none; display: block; text-align: center;">Sair</a>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <div class="header">
                <div>
                    <h2>Editar Cliente</h2>
                    <p>Atualizar informações do cliente</p>
                </div>
                <a href="/main/public/admin.php" class="btn btn-secondary">← Voltar</a>
            </div>

            <?php if ($success): ?>
                <div class="alert alert-success">
                    ✅ <?= htmlspecialchars($success) ?>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-error">
                    ❌ <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <!-- Formulário -->
            <div class="card" style="max-width: 600px;">
                <form method="POST" action="">
                    <div class="form-group">
                        <label class="form-label" for="name">Nome *</label>
                        <input
                            type="text"
                            id="name"
                            name="name"
                            class="form-input"
                            value="<?= htmlspecialchars($client['name']) ?>"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="email">E-mail *</label>
                        <input
                            type="email"
                            id="email"
                            name="email"
                            class="form-input"
                            value="<?= htmlspecialchars($client['email']) ?>"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="status">Status</label>
                        <select id="status" name="status" class="form-select">
                            <option value="active" <?= $client['status'] === 'active' ? 'selected' : '' ?>>Ativo</option>
                            <option value="inactive" <?= $client['status'] === 'inactive' ? 'selected' : '' ?>>Inativo</option>
                        </select>
                    </div>

                    <hr style="border: none; border-top: 1px solid rgba(255, 255, 255, 0.1); margin: 24px 0;">

                    <div class="form-group">
                        <label class="form-label" for="password">Nova Senha (opcional)</label>
                        <input
                            type="password"
                            id="password"
                            name="password"
                            class="form-input"
                            placeholder="Deixe em branco para manter a senha atual"
                        >
                        <small class="form-hint">Preencha apenas se desejar alterar a senha</small>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="confirm_password">Confirmar Nova Senha</label>
                        <input
                            type="password"
                            id="confirm_password"
                            name="confirm_password"
                            class="form-input"
                            placeholder="Repita a nova senha"
                        >
                    </div>

                    <div style="display: flex; gap: 16px; margin-top: 24px;">
                        <button type="submit" class="btn btn-primary">Salvar Alterações</button>
                        <a href="/main/public/admin.php" class="btn btn-secondary">Cancelar</a>
                    </div>
                </form>
            </div>
        </main>
    </div>
</body>
</html>
