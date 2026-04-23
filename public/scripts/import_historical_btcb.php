<?php
/**
 * Script: Import Historical BTCB Transactions and Prices
 * Esse script importa as transações dos CSVs fornecidos e busca preços históricos.
 */

require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/src/Utils/WeiConverter.php';

$walletAddress = '0x83c3D0A2945f914504928a94118Daff5b210b42c';
$csvFiles = [
    'c:\Users\Micro\Downloads\export-token-0x7130d2a12b9bcbfae4f2634d864a1ee1ce3ead9c.csv',
    'c:\Users\Micro\Downloads\export-token-0x7130d2a12b9bcbfae4f2634d864a1ee1ce3ead9c (1).csv'
];

try {
    $db = Database::getInstance()->getConnection();
    echo "Conectado ao banco.\n";

    // 1. Achar Wallet ID
    $stmt = $db->prepare("SELECT id FROM wallets WHERE address = ?");
    $stmt->execute([$walletAddress]);
    $walletId = $stmt->fetchColumn();

    if (!$walletId) {
        die("Erro: Carteira $walletAddress não encontrada no banco.\n");
    }
    echo "Wallet ID: $walletId\n";

    // 2. Importar Transações do CSV
    $importedCount = 0;
    foreach ($csvFiles as $csvPath) {
        if (!file_exists($csvPath)) {
            echo "Aviso: Arquivo $csvPath não encontrado.\n";
            continue;
        }

        echo "Processando $csvPath...\n";
        $handle = fopen($csvPath, "r");
        $headers = fgetcsv($handle); // Pular cabeçalho

        while (($data = fgetcsv($handle)) !== FALSE) {
            // CSV columns: Transaction Hash, Status, Method, BlockNo, DateTime (UTC), From, From_Nametag, To, To_Nametag, Amount, Value (USD)
            $txHash = $data[0];
            $status = strtolower($data[1]) === 'success' ? 'confirmed' : 'failed';
            $blockNumber = (int)$data[3];
            $timestamp = strtotime($data[4]);
            $from = strtolower($data[5]);
            $to = strtolower($data[7]);
            $amount = (float)$data[9];
            $usdValue = (float)str_replace(['$', ','], '', $data[10]);

            // Determinar tipo (simplificado para o import)
            $type = 'transfer';
            if (strpos($data[2], 'Swap') !== false) $type = 'swap';
            if (strpos($data[2], 'Deposit') !== false) $type = 'deposit';
            if (strpos($data[2], 'Withdraw') !== false) $type = 'withdraw';
            if (strpos($data[2], 'Supply') !== false) $type = 'deposit';
            if (strpos($data[2], 'Borrow') !== false) $type = 'deposit';

            try {
                $stmt = $db->prepare("
                    INSERT IGNORE INTO transactions_cache (
                        wallet_id, tx_hash, block_number, timestamp, from_address, to_address, 
                        value, token_symbol, token_name, transaction_type, usd_value_at_tx, status
                    ) VALUES (
                        ?, ?, ?, ?, ?, ?, ?, 'BTCB', 'Binance-Peg BTCB Token', ?, ?, ?
                    )
                ");
                $stmt->execute([
                    $walletId, $txHash, $blockNumber, $timestamp, $from, $to, 
                    $amount, $type, $usdValue, $status
                ]);
                if ($stmt->rowCount() > 0) $importedCount++;
            } catch (Exception $e) {
                echo "Erro ao inserir TX $txHash: " . $e->getMessage() . "\n";
            }
        }
        fclose($handle);
    }
    echo "Importação concluída: $importedCount novas transações inseridas.\n";

    // 3. Backfill Price History (BTCB = Bitcoin price)
    echo "Iniciando backfill de preços históricos (CoinGecko)...\n";
    
    // Pegar o range de datas necessário
    $stmt = $db->query("SELECT MIN(timestamp) FROM transactions_cache WHERE token_symbol = 'BTCB'");
    $minTimestamp = $stmt->fetchColumn();
    
    if ($minTimestamp) {
        $startDate = date('d-m-Y', $minTimestamp);
        echo "Buscando preços desde $startDate...\n";
        
        // Bitcoin ID na CoinGecko
        $cgId = 'bitcoin';
        
        // Loop por cada dia desde a primeira transação até hoje
        $current = $minTimestamp;
        $today = time();
        $pricesInserted = 0;
        
        while ($current <= $today) {
            $dateStr = date('d-m-Y', $current);
            $dbDate = date('Y-m-d', $current);
            
            // Verificar se já tem preço
            $check = $db->prepare("SELECT COUNT(*) FROM price_history WHERE token_symbol = 'BTCB' AND DATE(recorded_at) = ?");
            $check->execute([$dbDate]);
            if ($check->fetchColumn() == 0) {
                // Buscar na CoinGecko (Historical data)
                // Nota: Usar rate limit amigável
                $url = "https://api.coingecko.com/api/v3/coins/$cgId/history?date=$dateStr&localization=false";
                
                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
                curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                
                if ($httpCode === 200) {
                    $json = json_decode($response, true);
                    if (isset($json['market_data']['current_price']['usd'])) {
                        $priceUsd = $json['market_data']['current_price']['usd'];
                        
                        $ins = $db->prepare("INSERT INTO price_history (token_symbol, price_usd, recorded_at, source) VALUES (?, ?, ?, 'backfill')");
                        $ins->execute(['BTCB', $priceUsd, $dbDate . ' 12:00:00']);
                        $pricesInserted++;
                        echo "✓ $dbDate: $$priceUsd\n";
                    }
                } else if ($httpCode === 429) {
                    echo "⚠ Rate limit atingido. Aguardando 60s...\n";
                    sleep(60);
                    continue; // Repetir o mesmo dia
                } else {
                    echo "✗ Falha ao buscar preço para $dateStr (HTTP $httpCode)\n";
                }
                
                usleep(500000); // 0.5s entre reqs para evitar 429
            }
            
            $current += 86400; // Próximo dia
        }
        echo "Backfill de preços concluído: $pricesInserted dias inseridos.\n";
    }

} catch (Exception $e) {
    echo "ERRO FATAL: " . $e->getMessage() . "\n";
}
