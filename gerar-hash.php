<?php
/**
 * Gerar hash correto para CoinUp2026!
 * Execute este script para obter o hash correto
 */

$password = 'CoinUp2026!';
$hash = password_hash($password, PASSWORD_DEFAULT);

echo "Senha: $password\n";
echo "Hash correto: $hash\n\n";

// Verificar se o hash funciona
if (password_verify($password, $hash)) {
    echo "✅ Hash verificado com sucesso!\n";
} else {
    echo "❌ Erro na verificação!\n";
}

// SQL para atualizar no banco
echo "\n-- SQL para atualizar no banco:\n";
echo "UPDATE users SET password_hash = '$hash' WHERE email = 'admin@coinup.com.br';\n";
echo "UPDATE users SET password_hash = '$hash' WHERE email = 'cliente@coinup.com.br';\n";
?>
