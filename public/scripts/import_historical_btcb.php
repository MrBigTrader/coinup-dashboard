<?php
/**
 * Script: Import Historical BTCB Transactions and Prices (Web Upload)
 * Versão com interface de upload para facilitar a importação.
 */

require_once dirname(dirname(__DIR__)) . '/config/database.php';
require_once dirname(dirname(__DIR__)) . '/src/Utils/WeiConverter.php';

session_start();
// Opcional: Adicionar verificação de auth aqui se necessário
// require_once dirname(dirname(__DIR__)) . '/config/auth.php';

$walletAddress = '0x83c3D0A2945f914504928a94118Daff5b210b42c';
$output = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_files'])) {
    try {
        $db = Database::getInstance()->getConnection();
        $output .= "Conectado ao banco.\n";

        // 1. Achar Wallet ID
        $stmt = $db->prepare("SELECT id FROM wallets WHERE address = ?");
        $stmt->execute([$walletAddress]);
        $walletId = $stmt->fetchColumn();

        if (!$walletId) {
            throw new Exception("Carteira $walletAddress não encontrada no banco.");
        }
        $output .= "Wallet ID: $walletId\n";

        $importedCount = 0;
        $totalFiles = count($_FILES['csv_files']['name']);

        for ($i = 0; $i < $totalFiles; $i++) {
            $tmpPath = $_FILES['csv_files']['tmp_name'][$i];
            $fileName = $_FILES['csv_files']['name'][$i];

            if (empty($tmpPath)) continue;

            $output .= "Processando arquivo: $fileName...\n";
            $handle = fopen($tmpPath, "r");
            $headers = fgetcsv($handle); // Pular cabeçalho

            while (($data = fgetcsv($handle)) !== FALSE) {
                if (count($data) < 11) continue;

                $txHash = $data[0];
                $status = strtolower($data[1]) === 'success' ? 'confirmed' : 'failed';
                $blockNumber = (int)$data[3];
                $timestamp = strtotime($data[4]);
                $from = strtolower($data[5]);
                $to = strtolower($data[7]);
                $amount = (float)$data[9];
                $usdValue = (float)str_replace(['$', ','], '', $data[10]);

                $type = 'transfer';
                $method = $data[2];
                if (strpos($method, 'Swap') !== false) $type = 'swap';
                elseif (strpos($method, 'Deposit') !== false) $type = 'deposit';
                elseif (strpos($method, 'Withdraw') !== false) $type = 'withdraw';
                elseif (strpos($method, 'Supply') !== false) $type = 'deposit';
                elseif (strpos($method, 'Borrow') !== false) $type = 'deposit';

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
            }
            fclose($handle);
        }
        $output .= "Importação de TXs concluída: $importedCount novos registros.\n";

        // 2. Backfill de Preços
        $output .= "\nIniciando backfill de preços (Bitcoin via CoinGecko)...\n";
        $stmt = $db->prepare("SELECT MIN(timestamp) FROM transactions_cache WHERE token_symbol = 'BTCB' AND wallet_id = ?");
        $stmt->execute([$walletId]);
        $minTimestamp = $stmt->fetchColumn();

        if ($minTimestamp) {
            $from = $minTimestamp;
            $to = time();
            
            $output .= "Buscando preços em lote (Market Chart Range)... ";
            $url = "https://api.coingecko.com/api/v3/coins/bitcoin/market_chart/range?vs_currency=usd&from=$from&to=$to";
            
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200) {
                $json = json_decode($response, true);
                if (isset($json['prices']) && is_array($json['prices'])) {
                    $pricesInserted = 0;
                    $output .= count($json['prices']) . " pontos encontrados.\n";
                    
                    // CoinGecko retorna um ponto a cada hora em ranges longos. 
                    // Vamos pegar apenas 1 ponto por dia (o primeiro de cada dia) para não sobrecarregar o banco.
                    $processedDates = [];
                    
                    $stmt = $db->prepare("INSERT IGNORE INTO price_history (token_symbol, price_usd, recorded_at, source) VALUES ('BTCB', ?, ?, 'backfill_range')");
                    
                    foreach ($json['prices'] as $priceData) {
                        $ts = $priceData[0] / 1000; // ms to s
                        $price = $priceData[1];
                        $date = date('Y-m-d', $ts);
                        
                        if (!isset($processedDates[$date])) {
                            $stmt->execute([$price, $date . ' 12:00:00']);
                            if ($stmt->rowCount() > 0) $pricesInserted++;
                            $processedDates[$date] = true;
                        }
                    }
                    $output .= "Backfill concluído: $pricesInserted dias novos inseridos.\n";
                }
            } else {
                $output .= "Falha ao buscar preços em lote (HTTP $httpCode). Resposta: " . substr($response, 0, 100) . "...\n";
            }
        }
        $output .= "\nPROCEDIMENTO CONCLUÍDO COM SUCESSO!";

    } catch (Exception $e) {
        $output .= "\nERRO: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Importador Histórico BTCB</title>
    <style>
        body { font-family: sans-serif; background: #0f172a; color: #f8fafc; padding: 40px; }
        .card { background: #1e293b; padding: 30px; border-radius: 12px; max-width: 800px; margin: 0 auto; box-shadow: 0 10px 25px rgba(0,0,0,0.3); }
        h1 { color: #a78bfa; margin-top: 0; }
        pre { background: #000; padding: 20px; border-radius: 8px; color: #10b981; overflow-x: auto; white-space: pre-wrap; font-size: 13px; border: 1px solid #334155; }
        .btn { background: #7c3aed; color: white; border: none; padding: 12px 24px; border-radius: 6px; cursor: pointer; font-weight: bold; }
        .btn:hover { background: #6d28d9; }
        input[type="file"] { margin: 20px 0; display: block; }
    </style>
</head>
<body>
    <div class="card">
        <h1>Importação Histórica BTCB</h1>
        <p>Selecione os arquivos CSV de exportação da BSCScan para importar as transações de BTCB e preencher os preços de mercado de 2025/2026.</p>
        
        <form method="POST" enctype="multipart/form-data">
            <input type="file" name="csv_files[]" multiple accept=".csv" required>
            <button type="submit" class="btn">Iniciar Importação</button>
        </form>

        <?php if ($output): ?>
            <h2 style="margin-top: 30px;">Log de Execução:</h2>
            <pre><?php echo htmlspecialchars($output); ?></pre>
            <p>✅ Se o log terminou em "SUCESSO", você já pode fechar esta página e atualizar o seu Dashboard.</p>
        <?php endif; ?>
    </div>
</body>
</html>
