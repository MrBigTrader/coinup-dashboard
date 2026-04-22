<?php
require_once dirname(__DIR__) . '/config/database.php';

echo "Iniciando backfill de preços históricos de transações...\n";

try {
    $db = Database::getInstance()->getConnection();
    
    $stmt = $db->query("
        SELECT id, token_symbol, timestamp, value, transaction_type
        FROM transactions_cache
        WHERE (usd_value_at_tx IS NULL OR usd_value_at_tx = 0)
        AND value > 0
    ");
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Encontradas " . count($transactions) . " transações para backfill.\n";
    
    // Mapeamento básico para símbolos mais comuns
    $symbolMap = [
        'BTCB' => 'BTC',
        'WBNB' => 'BNB',
        'WETH' => 'ETH',
        'WMATIC' => 'MATIC',
    ];
    
    // Cache em memória para evitar chamadas duplicadas para o mesmo token no mesmo dia
    $priceCache = [];
    
    $updatedCount = 0;
    
    foreach ($transactions as $tx) {
        $symbol = strtoupper($tx['token_symbol']);
        $originalSymbol = $symbol;
        
        // Aplica o map
        if (isset($symbolMap[$symbol])) {
            $symbol = $symbolMap[$symbol];
        }
        
        // Remove prefixo de algumas redes obscuras se for o caso
        if (strpos($symbol, '.') !== false) {
            $symbol = explode('.', $symbol)[0];
        }
        
        $timestamp = (int)$tx['timestamp'];
        $dateStr = date('Y-m-d', $timestamp);
        $cacheKey = "{$symbol}_{$dateStr}";
        
        $price = 0;
        
        if (isset($priceCache[$cacheKey])) {
            $price = $priceCache[$cacheKey];
        } else {
            // Cryptocompare Historical API
            $url = "https://min-api.cryptocompare.com/data/pricehistorical?fsym={$symbol}&tsyms=USD&ts={$timestamp}";
            $response = @file_get_contents($url);
            
            if ($response) {
                $data = json_decode($response, true);
                if (isset($data[$symbol]['USD'])) {
                    $price = (float)$data[$symbol]['USD'];
                    $priceCache[$cacheKey] = $price;
                    echo "Fetched $symbol on $dateStr: $" . $price . "\n";
                } else {
                    echo "Price not found for $symbol on $dateStr\n";
                    $priceCache[$cacheKey] = 0; // Previne spammar a API
                }
            }
            
            // Sleep 300ms to avoid rate limits
            usleep(300000);
        }
        
        if ($price > 0) {
            $value = (float)$tx['value'];
            $usdValueAtTx = $value * $price;
            
            $updateStmt = $db->prepare("UPDATE transactions_cache SET usd_value_at_tx = ? WHERE id = ?");
            $updateStmt->execute([$usdValueAtTx, $tx['id']]);
            $updatedCount++;
        }
    }
    
    echo "Backfill concluído! $updatedCount transações atualizadas com sucesso.\n";
    
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage() . "\n";
}
