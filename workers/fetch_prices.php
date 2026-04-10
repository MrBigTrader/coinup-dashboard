<?php
/**
 * Worker: Fetch Prices
 * Responsável por buscar preços de criptomoedas da CoinGecko API
 *
 * Uso via cron:
 * */15 * * * * php /path/to/workers/fetch_prices.php
 */

require_once dirname(__DIR__) . '/config/database.php';

// Configurar para rodar em CLI
if (php_sapi_name() !== 'cli') {
    die('Este script só pode ser executado via linha de comando.');
}

echo "[" . date('Y-m-d H:i:s') . "] Iniciando busca de preços...\n";

$start_time = microtime(true);

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    echo "[ERRO] Falha na conexão com banco de dados: " . $e->getMessage() . "\n";
    exit(1);
}

$coingecko_api_key = getenv('COINGECKO_API_KEY');
$coingecko_base_url = 'https://pro-api.coingecko.com/api/v3';

// Lista de tokens para monitorar (pode ser expandida)
$tokens_to_fetch = [
    'ethereum' => [
        'symbol' => 'ETH',
        'name' => 'Ethereum',
        'coingecko_id' => 'ethereum'
    ],
    'binancecoin' => [
        'symbol' => 'BNB',
        'name' => 'BNB',
        'coingecko_id' => 'binancecoin'
    ],
    'arbitrum' => [
        'symbol' => 'ARB',
        'name' => 'Arbitrum',
        'coingecko_id' => 'arbitrum'
    ],
    'base' => [
        'symbol' => 'ETH',
        'name' => 'Ethereum (Base)',
        'coingecko_id' => 'ethereum'
    ],
    'matic-network' => [
        'symbol' => 'MATIC',
        'name' => 'Polygon',
        'coingecko_id' => 'matic-network'
    ],
    'bitcoin' => [
        'symbol' => 'BTC',
        'name' => 'Bitcoin',
        'coingecko_id' => 'bitcoin'
    ],
    'usd-coin' => [
        'symbol' => 'USDC',
        'name' => 'USD Coin',
        'coingecko_id' => 'usd-coin'
    ],
    'tether' => [
        'symbol' => 'USDT',
        'name' => 'Tether',
        'coingecko_id' => 'tether'
    ],
];

echo "[" . date('Y-m-d H:i:s') . "] Buscando preços para " . count($tokens_to_fetch) . " tokens...\n";

$updated_count = 0;
$error_count = 0;

foreach ($tokens_to_fetch as $coingecko_id => $token_info) {
    echo "  [INFO] Buscando preço de {$token_info['symbol']}...\n";

    try {
        // Buscar preço da CoinGecko
        $price_data = fetch_price_from_coingecko(
            $coingecko_base_url,
            $coingecko_api_key,
            $coingecko_id
        );

        if (!$price_data) {
            echo "    [ERRO] Não foi possível obter dados\n";
            $error_count++;
            continue;
        }

        $price_usd = $price_data['usd'] ?? 0;
        $price_brl = $price_data['brl'] ?? 0;
        $change_24h = $price_data['usd_24h_change'] ?? 0;
        $market_cap = $price_data['usd_market_cap'] ?? 0;

        // Salvar/atualizar no banco
        $stmt = $db->prepare("
            INSERT INTO token_prices (
                token_symbol, token_name, coingecko_id,
                price_usd, price_brl, change_24h, market_cap_usd,
                last_updated
            ) VALUES (
                ?, ?, ?,
                ?, ?, ?, ?,
                NOW()
            )
            ON DUPLICATE KEY UPDATE
                price_usd = VALUES(price_usd),
                price_brl = VALUES(price_brl),
                change_24h = VALUES(change_24h),
                market_cap_usd = VALUES(market_cap_usd),
                last_updated = NOW()
        ");

        $stmt->execute([
            $token_info['symbol'],
            $token_info['name'],
            $coingecko_id,
            $price_usd,
            $price_brl,
            $change_24h,
            $market_cap
        ]);

        echo "    [SUCESSO] USD: $ " . number_format($price_usd, 2) . " | BRL: R$ " . number_format($price_brl, 2) . " | 24h: " . number_format($change_24h, 2) . "%\n";
        $updated_count++;

    } catch (Exception $e) {
        echo "    [ERRO] " . $e->getMessage() . "\n";
        $error_count++;
    }
}

$duration = microtime(true) - $start_time;

echo "\n";
echo "[" . date('Y-m-d H:i:s') . "] Busca de preços concluída!\n";
echo "  Tokens atualizados: $updated_count\n";
echo "  Erros: $error_count\n";
echo "  Tempo total: " . round($duration, 2) . "s\n";

/**
 * Buscar preço da CoinGecko API
 */
function fetch_price_from_coingecko($base_url, $api_key, $coingecko_id) {
    $url = "$base_url/simple/price";
    $params = [
        'ids' => $coingecko_id,
        'vs_currencies' => 'usd,brl',
        'include_24hr_change' => 'true',
        'include_market_cap' => 'true'
    ];

    $url .= '?' . http_build_query($params);

    $headers = [
        'Accept: application/json',
        'x-cg-pro-api-key: ' . $api_key
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code !== 200 || !$response) {
        return false;
    }

    $data = json_decode($response, true);

    if (isset($data[$coingecko_id])) {
        return $data[$coingecko_id];
    }

    return false;
}
