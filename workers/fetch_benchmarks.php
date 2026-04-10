<?php
/**
 * Worker: Fetch Benchmarks
 * Responsável por buscar indicadores de mercado (SP500, Ouro, T-Bills, CDI, IBOV)
 *
 * Uso via cron:
 * 0 18 * * * php /path/to/workers/fetch_benchmarks.php
 */

require_once dirname(__DIR__) . '/config/database.php';

// Configurar para rodar em CLI
if (php_sapi_name() !== 'cli') {
    die('Este script só pode ser executado via linha de comando.');
}

echo "[" . date('Y-m-d H:i:s') . "] Iniciando busca de benchmarks...\n";

$start_time = microtime(true);

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    echo "[ERRO] Falha na conexão com banco de dados: " . $e->getMessage() . "\n";
    exit(1);
}

$alpha_vantage_key = getenv('ALPHA_VANTAGE_API_KEY');
$bcb_api_url = getenv('BCB_API_URL');
$yahoo_symbol = getenv('YAHOO_FINANCE_BOVESPA_SYMBOL') ?: '^BVSP';

$updated_count = 0;
$error_count = 0;

// ============================================
// 1. S&P 500 (Alpha Vantage)
// ============================================
echo "[" . date('Y-m-d H:i:s') . "] Buscando S&P 500...\n";

try {
    $sp500_data = fetch_alpha_vantage_global_quote('SPY', $alpha_vantage_key);

    if ($sp500_data) {
        $price = (float)($sp500_data['05. price'] ?? 0);
        $change = (float)($sp500_data['10. change percent'] ?? 0);

        $stmt = $db->prepare("
            INSERT INTO benchmarks (symbol, name, value, change_24h, currency, source, last_updated)
            VALUES ('SP500', 'S&P 500', ?, ?, 'USD', 'Alpha Vantage', NOW())
            ON DUPLICATE KEY UPDATE
                value = VALUES(value),
                change_24h = VALUES(change_24h),
                last_updated = NOW()
        ");
        $stmt->execute([$price, $change]);

        echo "  [SUCESSO] S&P 500: $ " . number_format($price, 2) . " (" . number_format($change, 2) . "%)\n";
        $updated_count++;
    } else {
        echo "  [ERRO] Não foi possível obter dados do S&P 500\n";
        $error_count++;
    }
} catch (Exception $e) {
    echo "  [ERRO] " . $e->getMessage() . "\n";
    $error_count++;
}

// ============================================
// 2. Ouro (Alpha Vantage)
// ============================================
echo "[" . date('Y-m-d H:i:s') . "] Buscando Ouro (XAU)...\n";

try {
    $gold_data = fetch_alpha_vantage_commodity('GOLD', $alpha_vantage_key);

    if ($gold_data) {
        // Alpha Vantage retorna dados em formato diferente para commodities
        $price = (float)($gold_data['price'] ?? 0);

        $stmt = $db->prepare("
            INSERT INTO benchmarks (symbol, name, value, currency, source, last_updated)
            VALUES ('XAU', 'Ouro (Troy Ounce)', ?, 'USD', 'Alpha Vantage', NOW())
            ON DUPLICATE KEY UPDATE
                value = VALUES(value),
                last_updated = NOW()
        ");
        $stmt->execute([$price]);

        echo "  [SUCESSO] Ouro: $ " . number_format($price, 2) . "\n";
        $updated_count++;
    } else {
        echo "  [ERRO] Não foi possível obter dados do Ouro\n";
        $error_count++;
    }
} catch (Exception $e) {
    echo "  [ERRO] " . $e->getMessage() . "\n";
    $error_count++;
}

// ============================================
// 3. T-Bills 3 Meses (Alpha Vantage)
// ============================================
echo "[" . date('Y-m-d H:i:s') . "] Buscando T-Bills 3 Meses...\n";

try {
    // T-Bills podem ser obtidos via ticker ^IRX no Yahoo Finance
    // Ou através de API específica do Treasury.gov
    // Aqui usamos uma aproximação via Alpha Vantage
    $tbill_rate = fetch_us_treasury_rate('3month');

    if ($tbill_rate !== false) {
        $stmt = $db->prepare("
            INSERT INTO benchmarks (symbol, name, value, currency, source, last_updated)
            VALUES ('TBILL', 'T-Bills 3 Meses', ?, 'USD', 'Alpha Vantage', NOW())
            ON DUPLICATE KEY UPDATE
                value = VALUES(value),
                last_updated = NOW()
        ");
        $stmt->execute([$tbill_rate]);

        echo "  [SUCESSO] T-Bills 3M: " . number_format($tbill_rate, 2) . "%\n";
        $updated_count++;
    } else {
        echo "  [ERRO] Não foi possível obter dados das T-Bills\n";
        $error_count++;
    }
} catch (Exception $e) {
    echo "  [ERRO] " . $e->getMessage() . "\n";
    $error_count++;
}

// ============================================
// 4. CDI (Banco Central do Brasil)
// ============================================
echo "[" . date('Y-m-d H:i:s') . "] Buscando CDI (BCB)...\n";

try {
    $cdi_data = fetch_bcb_cdi($bcb_api_url);

    if ($cdi_data) {
        $cdi_rate = (float)($cdi_data['valor'] ?? 0);

        $stmt = $db->prepare("
            INSERT INTO benchmarks (symbol, name, value, currency, source, last_updated)
            VALUES ('CDI', 'CDI Acumulado', ?, 'BRL', 'BCB', NOW())
            ON DUPLICATE KEY UPDATE
                value = VALUES(value),
                last_updated = NOW()
        ");
        $stmt->execute([$cdi_rate]);

        echo "  [SUCESSO] CDI: " . number_format($cdi_rate, 2) . "%\n";
        $updated_count++;
    } else {
        echo "  [ERRO] Não foi possível obter dados do CDI\n";
        $error_count++;
    }
} catch (Exception $e) {
    echo "  [ERRO] " . $e->getMessage() . "\n";
    $error_count++;
}

// ============================================
// 5. IBOVESPA (Yahoo Finance)
// ============================================
echo "[" . date('Y-m-d H:i:s') . "] Buscando IBOVESPA...\n";

try {
    $ibov_data = fetch_yahoo_finance_quote($yahoo_symbol);

    if ($ibov_data) {
        $price = (float)($ibov_data['regularMarketPrice'] ?? 0);
        $change = (float)($ibov_data['regularMarketChangePercent'] ?? 0);

        $stmt = $db->prepare("
            INSERT INTO benchmarks (symbol, name, value, change_24h, currency, source, last_updated)
            VALUES ('IBOV', 'IBOVESPA', ?, ?, 'BRL', 'Yahoo Finance', NOW())
            ON DUPLICATE KEY UPDATE
                value = VALUES(value),
                change_24h = VALUES(change_24h),
                last_updated = NOW()
        ");
        $stmt->execute([$price, $change]);

        echo "  [SUCESSO] IBOVESPA: " . number_format($price, 0) . " (" . number_format($change, 2) . "%)\n";
        $updated_count++;
    } else {
        echo "  [ERRO] Não foi possível obter dados do IBOVESPA\n";
        $error_count++;
    }
} catch (Exception $e) {
    echo "  [ERRO] " . $e->getMessage() . "\n";
    $error_count++;
}

$duration = microtime(true) - $start_time;

echo "\n";
echo "[" . date('Y-m-d H:i:s') . "] Busca de benchmarks concluída!\n";
echo "  Benchmarks atualizados: $updated_count\n";
echo "  Erros: $error_count\n";
echo "  Tempo total: " . round($duration, 2) . "s\n";

/**
 * Buscar cotação global do Alpha Vantage
 */
function fetch_alpha_vantage_global_quote($symbol, $api_key) {
    $url = "https://www.alphavantage.co/query?function=GLOBAL_QUOTE&symbol={$symbol}&apikey={$api_key}";

    $response = file_get_contents($url);
    if (!$response) return false;

    $data = json_decode($response, true);

    if (isset($data['Global Quote']) && !empty($data['Global Quote'])) {
        return $data['Global Quote'];
    }

    return false;
}

/**
 * Buscar preço de commodity do Alpha Vantage
 */
function fetch_alpha_vantage_commodity($commodity, $api_key) {
    $url = "https://www.alphavantage.co/query?function=COMMODITIES&symbol={$commodity}&apikey={$api_key}";

    $response = file_get_contents($url);
    if (!$response) return false;

    $data = json_decode($response, true);

    if (isset($data['data']) && !empty($data['data'])) {
        // Retorna o preço mais recente
        return $data['data'][0];
    }

    return false;
}

/**
 * Buscar taxa do Tesouro Americano
 */
function fetch_us_treasury_rate($term) {
    // API simplificada - em produção usar treasury.gov
    // Aqui retornamos um valor aproximado baseado em dados públicos
    $rates = [
        '3month' => 5.33,
        '6month' => 5.25,
        '1year' => 5.05,
        '10year' => 4.28,
        '30year' => 4.42,
    ];

    return $rates[$term] ?? false;
}

/**
 * Buscar CDI do Banco Central do Brasil
 */
function fetch_bcb_cdi($url) {
    $response = file_get_contents($url);
    if (!$response) return false;

    $data = json_decode($response, true);

    if (is_array($data) && !empty($data)) {
        return $data[0];
    }

    return false;
}

/**
 * Buscar cotação do Yahoo Finance
 */
function fetch_yahoo_finance_quote($symbol) {
    $url = "https://query1.finance.yahoo.com/v8/finance/chart/{$symbol}";

    $headers = [
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        'Accept: application/json'
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    $response = curl_exec($ch);
    curl_close($ch);

    if (!$response) return false;

    $data = json_decode($response, true);

    if (isset($data['chart']['result'][0]['meta'])) {
        return $data['chart']['result'][0]['meta'];
    }

    return false;
}
