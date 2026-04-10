<?php
/**
 * Página de Login - CoinUp Dashboard
 * Tema escuro com identidade visual CoinUp
 */

require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/auth.php';
require_once dirname(__DIR__) . '/config/middleware.php';

// Redirecionar se já estiver logado
Middleware::redirectIfLoggedIn();

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error = 'Por favor, preencha e-mail e senha.';
    } else {
        $auth = Auth::getInstance();
        $result = $auth->login($email, $password);

        if ($result['success']) {
            // Redirecionar conforme perfil
            if ($result['user']['role'] === 'admin') {
                header('Location: /main/public/admin.php');
                exit;
            } else {
                header('Location: /main/public/dashboard.php');
                exit;
            }
        } else {
            $error = $result['message'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - CoinUp Dashboard</title>
    <link rel="stylesheet" href="/main/assets/css/style.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .login-container {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            padding: 40px;
            width: 100%;
            max-width: 400px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
        }

        .logo {
            text-align: center;
            margin-bottom: 30px;
        }

        .logo h1 {
            color: #fff;
            font-size: 2.5rem;
            font-weight: 700;
            background: linear-gradient(135deg, #a855f7, #3b82f6);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .logo p {
            color: #94a3b8;
            font-size: 0.9rem;
            margin-top: 5px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            color: #e2e8f0;
            margin-bottom: 8px;
            font-weight: 500;
        }

        .form-group input {
            width: 100%;
            padding: 12px 16px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            color: #fff;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .form-group input:focus {
            outline: none;
            border-color: #a855f7;
            background: rgba(255, 255, 255, 0.08);
        }

        .form-group input::placeholder {
            color: #64748b;
        }

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
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 20px rgba(168, 85, 247, 0.4);
        }

        .error-message {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #fca5a5;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 0.9rem;
        }

        .footer {
            text-align: center;
            margin-top: 20px;
            color: #64748b;
            font-size: 0.85rem;
        }

        .test-credentials {
            margin-top: 30px;
            padding: 15px;
            background: rgba(59, 130, 246, 0.1);
            border: 1px solid rgba(59, 130, 246, 0.2);
            border-radius: 10px;
            font-size: 0.85rem;
        }

        .test-credentials h4 {
            color: #60a5fa;
            margin-bottom: 10px;
        }

        .test-credentials p {
            color: #94a3b8;
            margin: 5px 0;
        }

        .test-credentials code {
            background: rgba(0, 0, 0, 0.3);
            padding: 2px 6px;
            border-radius: 4px;
            color: #a855f7;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="logo">
            <h1>🪙 CoinUp</h1>
            <p>Dashboard de Investimentos DeFi</p>
        </div>

        <?php if ($error): ?>
            <div class="error-message">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label for="email">E-mail</label>
                <input 
                    type="email" 
                    id="email" 
                    name="email" 
                    placeholder="seu@email.com"
                    value="<?= htmlspecialchars($email) ?>"
                    required
                >
            </div>

            <div class="form-group">
                <label for="password">Senha</label>
                <input 
                    type="password" 
                    id="password" 
                    name="password" 
                    placeholder="••••••••"
                    required
                >
            </div>

            <button type="submit" class="btn-login">Entrar</button>
        </form>

        <div class="footer">
            <p>© 2026 CoinUp. Todos os direitos reservados.</p>
        </div>

        <div class="test-credentials">
            <h4>🔐 Credenciais de Teste</h4>
            <p><strong>Admin:</strong> <code>admin@coinup.com.br</code></p>
            <p><strong>Cliente:</strong> <code>cliente@coinup.com.br</code></p>
            <p><strong>Senha:</strong> <code>CoinUp2026!</code></p>
        </div>
    </div>
</body>
</html>
