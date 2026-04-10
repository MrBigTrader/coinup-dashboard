<?php
/**
 * Página de Erro - CoinUp Dashboard
 */
$code = $_GET['code'] ?? '500';
$messages = [
    '403' => ['Acesso Negado', 'Você não tem permissão para acessar esta página.'],
    '404' => ['Página Não Encontrada', 'A página que você está procurando não existe.'],
    '500' => ['Erro Interno', 'Ocorreu um erro no servidor. Tente novamente mais tarde.'],
];

list($title, $message) = $messages[$code] ?? ['Erro', 'Ocorreu um erro inesperado.'];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Erro <?= $code ?> - CoinUp</title>
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
            color: #e2e8f0;
        }

        .error-container {
            text-align: center;
            padding: 40px;
        }

        .error-code {
            font-size: 6rem;
            font-weight: 700;
            background: linear-gradient(135deg, #a855f7, #3b82f6);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .error-title {
            font-size: 1.5rem;
            color: #fff;
            margin: 20px 0 10px;
        }

        .error-message {
            color: #94a3b8;
            margin-bottom: 30px;
        }

        .btn-home {
            padding: 12px 24px;
            background: linear-gradient(135deg, #a855f7, #3b82f6);
            border: none;
            border-radius: 10px;
            color: #fff;
            text-decoration: none;
            font-weight: 600;
            display: inline-block;
            transition: transform 0.2s ease;
        }

        .btn-home:hover {
            transform: translateY(-2px);
        }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-code"><?= $code ?></div>
        <h1 class="error-title"><?= $title ?></h1>
        <p class="error-message"><?= $message ?></p>
        <a href="/main/public/login.php" class="btn-home">Voltar ao Login</a>
    </div>
</body>
</html>
