<?php
/**
 * Atualizar Senhas - CoinUp
 * Execute este arquivo via browser para corrigir as senhas
 * URL: https://coinup.com.br/main/public/fix-passwords.php
 */

// Caminho para o .env
$envFile = __DIR__ . '/../.env';

// Carregar .env
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') === false) continue;
        list($name, $value) = explode('=', $line, 2);
        putenv(trim($name) . '=' . trim($value, '"\''));
    }
}

require_once __DIR__ . '/../config/database.php';

echo "<!DOCTYPE html>
<html>
<head>
    <title>Corrigir Senhas - CoinUp</title>
    <style>
        body { font-family: monospace; background: #1a1a2e; color: #e2e8f0; padding: 20px; }
        .ok { color: #4ade80; }
        .error { color: #f87171; }
        pre { background: rgba(0,0,0,0.3); padding: 15px; border-radius: 8px; overflow-x: auto; }
    </style>
</head>
<body>
<h1>🔧 Corrigir Senhas dos Usuários</h1>
";

try {
    $db = Database::getInstance()->getConnection();
    echo "<p class='ok'>✅ Conexão com banco OK</p>";
    
    // Gerar hash para CoinUp2026!
    $password = 'CoinUp2026!';
    $hash = password_hash($password, PASSWORD_DEFAULT);
    
    echo "<h2>Hash Gerado:</h2>";
    echo "<pre>$hash</pre>";
    
    // Verificar hash
    if (password_verify($password, $hash)) {
        echo "<p class='ok'>✅ Hash verificado com sucesso!</p>";
    } else {
        echo "<p class='error'>❌ Erro na verificação do hash!</p>";
        exit;
    }
    
    // Atualizar admin
    $stmt = $db->prepare("UPDATE users SET password_hash = ? WHERE email = 'admin@coinup.com.br'");
    $stmt->execute([$hash]);
    echo "<p class='ok'>✅ Senha do admin atualizada</p>";
    
    // Atualizar cliente
    $stmt = $db->prepare("UPDATE users SET password_hash = ? WHERE email = 'cliente@coinup.com.br'");
    $stmt->execute([$hash]);
    echo "<p class='ok'>✅ Senha do cliente atualizada</p>";
    
    // Verificar no banco
    echo "<h2>Verificação:</h2>";
    $stmt = $db->query("SELECT email, role, LEFT(password_hash, 20) as hash_preview FROM users");
    $users = $stmt->fetchAll();
    
    echo "<pre>";
    foreach ($users as $u) {
        echo "{$u['email']} ({$u['role']}) - Hash: {$u['hash_preview']}...\n";
    }
    echo "</pre>";
    
    echo "<h2 class='ok'>✅ Senhas corrigidas com sucesso!</h2>";
    echo "<p>Agora você pode logar com:</p>";
    echo "<ul>";
    echo "<li><strong>Admin:</strong> admin@coinup.com.br / CoinUp2026!</li>";
    echo "<li><strong>Cliente:</strong> cliente@coinup.com.br / CoinUp2026!</li>";
    echo "</ul>";
    echo "<p><a href='/main/public/login-simples.php' style='color: #a855f7;'>Ir para Login</a></p>";
    
} catch (Exception $e) {
    echo "<p class='error'>❌ Erro: " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "</body></html>";
?>
