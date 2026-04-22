<?php
require_once __DIR__ . '/../config/database.php';
try {
    $db = Database::getInstance()->getConnection();
    
    // Pegar o último snapshot válido para cada usuário
    $stmt = $db->query("SELECT user_id, total_value_usd, total_value_brl FROM portfolio_history WHERE date = CURDATE()");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($users)) {
        // Se não tem snapshot hoje, pega dos saldos atuais
        $stmt = $db->query("
            SELECT w.user_id, SUM(wb.balance_usd) as total_usd 
            FROM wallet_balances wb 
            JOIN wallets w ON wb.wallet_id = w.id 
            WHERE w.is_active = 1 
            GROUP BY w.user_id
        ");
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($users as &$u) {
            $u['total_value_usd'] = $u['total_usd'];
            $u['total_value_brl'] = $u['total_usd'] * 5; // Estimativa
        }
    }

    $db->beginTransaction();
    $insertStmt = $db->prepare("
        INSERT IGNORE INTO portfolio_history (user_id, date, total_value_usd, total_value_brl)
        VALUES (?, DATE_SUB(CURDATE(), INTERVAL ? DAY), ?, ?)
    ");

    foreach ($users as $u) {
        $baseUsd = $u['total_value_usd'];
        $baseBrl = $u['total_value_brl'];
        
        // Gerar 30 dias de histórico simulando volatilidade passada
        for ($i = 30; $i > 0; $i--) {
            // Volatilidade aleatória entre -3% e +3%
            $modifier = 1 - ($i * 0.005) + (mt_rand(-200, 200) / 10000); 
            $historicalUsd = $baseUsd * $modifier;
            $historicalBrl = $baseBrl * $modifier;
            
            $insertStmt->execute([$u['user_id'], $i, $historicalUsd, $historicalBrl]);
        }
    }
    $db->commit();
    echo "Histórico gerado com sucesso para " . count($users) . " usuários.\n";
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage();
}
