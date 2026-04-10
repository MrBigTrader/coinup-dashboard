<?php
/**
 * Login Simples - CoinUp Dashboard
 * Versão de emergência para debug
 */

// Habilitar exibição de erros para debug
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Caminhos absolutos
define('BASE_PATH', dirname(__DIR__));

// Carregar configurações básicas
require_once BASE_PATH . '/config/database.php';

// Iniciar sessão manualmente
if (session_status() === PHP_SESSION_NONE) {
    session_name('COINUPSESS');
    session_start();
}

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
        $password = $_POST['password'] ?? '';

        if (empty($email) || empty($password)) {
            $error = 'Por favor, preencha e-mail e senha.';
        } else {
            // Conexão direta com banco
            $db = Database::getInstance()->getConnection();
            
            $stmt = $db->prepare("SELECT id, name, email, password_hash, role, status FROM users WHERE email = ? AND status = 'active'");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if (!$user) {
                $error = 'E-mail ou senha inválidos';
            } elseif (!password_verify($password, $user['password_hash'])) {
                $error = 'E-mail ou senha inválidos';
            } else {
                // Login bem sucedido
                session_regenerate_id(true);
                
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_role'] = $user['role'];
                $_SESSION['logged_in'] = true;
                
                // Redirecionar
                if ($user['role'] === 'admin') {
                    header('Location: admin.php');
                } else {
                    header('Location: dashboard.php');
                }
                exit;
            }
        }
    } catch (Exception $e) {
        $error = 'Erro: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - CoinUp</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #e2e8f0;
        }
        .login-container {
            background: rgba(255,255,255,0.05);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 20px;
            padding: 40px;
            width: 100%;
            max-width: 400px;
        }
        .logo { text-align: center; margin-bottom: 30px; }
        .logo h1 {
            color: #fff;
            font-size: 2.5rem;
            background: linear-gradient(135deg, #a855f7, #3b82f6);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 500; }
        .form-group input {
            width: 100%;
            padding: 12px 16px;
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 10px;
            color: #fff;
            font-size: 1rem;
        }
        .form-group input:focus { outline: none; border-color: #a855f7; }
        .btn-login {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #a855f7, #3b82f6);
            border: none;
            border-radius: 10px;
            color: #fff;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
        }
        .error-message {
            background: rgba(239,68,68,0.1);
            border: 1px solid rgba(239,68,68,0.3);
            color: #fca5a5;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .test-credentials {
            margin-top: 30px;
            padding: 15px;
            background: rgba(59,130,246,0.1);
            border: 1px solid rgba(59,130,246,0.2);
            border-radius: 10px;
            font-size: 0.85rem;
        }
        .test-credentials code { color: #a855f7; }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="logo">
            <h1>🪙 CoinUp</h1>
            <p>Dashboard de Investimentos DeFi</p>
        </div>

        <?php if ($error): ?>
            <div class="error-message"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label>E-mail</label>
                <input type="email" name="email" value="<?= htmlspecialchars($email) ?>" required>
            </div>
            <div class="form-group">
                <label>Senha</label>
                <input type="password" name="password" required>
            </div>
            <button type="submit" class="btn-login">Entrar</button>
        </form>

        <div class="test-credentials">
            <strong>Credenciais de Teste:</strong><br>
            Admin: <code>admin@coinup.com.br</code><br>
            Cliente: <code>cliente@coinup.com.br</code><br>
            Senha: <code>CoinUp2026!</code>
        </div>
    </div>
</body>
</html>
