<?php
/**
 * Admin Client Add - CoinUp
 * Adicionar novo cliente
 */

require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/auth.php';
require_once dirname(__DIR__) . '/config/middleware.php';

Middleware::requireAuth();
Middleware::requireAdmin();

$auth = Auth::getInstance();
$user = $auth->getCurrentUser();

$db = Database::getInstance()->getConnection();

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Validações
    if (empty($name) || empty($email) || empty($password)) {
        $error = 'Preencha todos os campos obrigatórios.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'E-mail inválido.';
    } elseif (strlen($password) < 8) {
        $error = 'A senha deve ter pelo menos 8 caracteres.';
    } elseif ($password !== $confirm_password) {
        $error = 'As senhas não coincidem.';
    } else {
        try {
            // Verificar se e-mail já existe
            $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $error = 'Este e-mail já está cadastrado.';
            } else {
                // Criar usuário
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $db->prepare("
                    INSERT INTO users (name, email, password_hash, role, status)
                    VALUES (?, ?, ?, 'client', 'active')
                ");
                $stmt->execute([$name, $email, $password_hash]);

                $success = 'Cliente cadastrado com sucesso!';
                header('Location: /main/public/admin.php?success=client_added');
                exit;
            }
        } catch (Exception $e) {
            error_log("Erro ao criar cliente: " . $e->getMessage());
            $error = 'Erro ao cadastrar cliente. Tente novamente.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Novo Cliente - Admin - CoinUp</title>
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
                    <h2>Novo Cliente</h2>
                    <p>Cadastrar novo cliente no sistema</p>
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
                            value="<?= htmlspecialchars($_POST['name'] ?? '') ?>"
                            required
                            placeholder="Nome completo do cliente"
                        >
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="email">E-mail *</label>
                        <input
                            type="email"
                            id="email"
                            name="email"
                            class="form-input"
                            value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                            required
                            placeholder="cliente@exemplo.com"
                        >
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="password">Senha *</label>
                        <input
                            type="password"
                            id="password"
                            name="password"
                            class="form-input"
                            required
                            placeholder="Mínimo 8 caracteres"
                        >
                        <small class="form-hint">A senha deve ter pelo menos 8 caracteres</small>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="confirm_password">Confirmar Senha *</label>
                        <input
                            type="password"
                            id="confirm_password"
                            name="confirm_password"
                            class="form-input"
                            required
                            placeholder="Repita a senha"
                        >
                    </div>

                    <div style="display: flex; gap: 16px; margin-top: 24px;">
                        <button type="submit" class="btn btn-primary">Cadastrar Cliente</button>
                        <a href="/main/public/admin.php" class="btn btn-secondary">Cancelar</a>
                    </div>
                </form>
            </div>
        </main>
    </div>
</body>
</html>
