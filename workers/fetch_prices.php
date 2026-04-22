<?php
/**
 * Worker: Fetch Prices (CoinGecko API) - v2.0
 * ============================================================
 * Busca preços de TODOS os tokens monitorados em USD e BRL
 * usando batch request (1 chamada API para todos os tokens).
 *
 * Features:
 * - Batch request otimizado (economia de 87% de chamadas API)
 * - Retry com backoff exponencial
 * - Rate limiting inteligente
 * - Logging detalhado
 * - Validação de dados (outliers, zeros)
 * - Cache de último preço válido
 *
 * Uso via cron:
 * a cada 15 minutos: php /home2/coinup66/public_html/main/workers/fetch_prices.php
 *
 * Uso manual:
 * php workers/fetch_prices.php --verbose
 *
 * Revisão: 2026-04-10
 */

// ============================================================
// BOOTSTRAP
// ============================================================

require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/src/Utils/WeiConverter.php';

// Inicializar Database para carregar .env
Database::getInstance();

// Configurar para rodar apenas em CLI
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die('Este script só pode ser executado via linha de comando.');
}

// Parse de argumentos CLI
$verbose = in_array('--verbose', $_SERVER['argv']) || in_array('-v', $_SERVER['argv']);
$dry_run = in_array('--dry-run', $_SERVER['argv']);

// ============================================================
// CONFIGURAÇÃO
// ============================================================

$coingecko_api_key = $_ENV['COINGECKO_API_KEY'] ?? getenv('COINGECKO_API_KEY');
$coingecko_base_url = 'https://api.coingecko.com/api/v3'; // API gratuita (Demo)

if (empty($coingecko_api_key)) {
    echo "[ERRO] COINGECKO_API_KEY não configurada no .env\n";
    exit(1);
}

// Lista completa de tokens monitorados
// Referência: https://www.coingecko.com/api/documentação/v3#/simple/get_simple_price
$TOKENS = [
    // ===== Tokens Nativos das Redes =====
    'ethereum' => [
        'symbol' => 'ETH',
        'name' => 'Ethereum',
        'category' => 'native',
        'networks' => ['ethereum']
    ],
    'binancecoin' => [
        'symbol' => 'BNB',
        'name' => 'BNB',
        'category' => 'native',
        'networks' => ['bnb']
    ],
    'arbitrum' => [
        'symbol' => 'ARB',
        'name' => 'Arbitrum',
        'category' => 'native',
        'networks' => ['arbitrum']
    ],
    // Base usa ETH como token nativo
    'matic-network' => [
        'symbol' => 'MATIC',
        'name' => 'Polygon',
        'category' => 'native',
        'networks' => ['polygon']
    ],

    // ===== Bitcoin Wrappers =====
    'bitcoin' => [
        'symbol' => 'WBTC',
        'name' => 'Wrapped Bitcoin',
        'category' => 'wrapper',
        'networks' => ['ethereum', 'arbitrum', 'base', 'polygon'],
        'contracts' => [
            'ethereum' => '0x2260FAC5E5542a773Aa44fBCfeDf7C193bc2C599',
        ]
    ],

    // ===== Stablecoins =====
    'tether' => [
        'symbol' => 'USDT',
        'name' => 'Tether USD',
        'category' => 'stablecoin',
        'networks' => ['ethereum', 'bnb', 'arbitrum', 'base', 'polygon']
    ],
    'usd-coin' => [
        'symbol' => 'USDC',
        'name' => 'USD Coin',
        'category' => 'stablecoin',
        'networks' => ['ethereum', 'bnb', 'arbitrum', 'base', 'polygon']
    ],
    'dai' => [
        'symbol' => 'DAI',
        'name' => 'Dai',
        'category' => 'stablecoin',
        'networks' => ['ethereum', 'bnb', 'arbitrum', 'polygon']
    ],
    'binance-usd' => [
        'symbol' => 'BUSD',
        'name' => 'Binance USD',
        'category' => 'stablecoin',
        'networks' => ['bnb']
    ],

    // ===== RWA (Ondo Finance) =====
    'ondo-finance' => [
        'symbol' => 'ONDO',
        'name' => 'Ondo',
        'category' => 'rwa',
        'networks' => ['ethereum']
    ],
    // Tokens Ondo específicos (ASMLon, SLVon, TSMSon)
    // Usar busca manual se não estiverem na CoinGecko

    // ===== Ouro Digital =====
    'tether-gold' => [
        'symbol' => 'XAUT',
        'name' => 'Tether Gold',
        'category' => 'commodity',
        'networks' => ['ethereum']
    ],

    // ===== DeFi Tokens =====
    'aave' => [
        'symbol' => 'AAVE',
        'name' => 'Aave',
        'category' => 'defi',
        'networks' => ['ethereum', 'bnb', 'arbitrum', 'polygon']
    ],
    'uniswap' => [
        'symbol' => 'UNI',
        'name' => 'Uniswap',
        'category' => 'defi',
        'networks' => ['ethereum', 'arbitrum', 'polygon']
    ],
    'pancakeswap-token' => [
        'symbol' => 'CAKE',
        'name' => 'PancakeSwap',
        'category' => 'defi',
        'networks' => ['bnb']
    ],
    'venus' => [
        'symbol' => 'XVS',
        'name' => 'Venus',
        'category' => 'defi',
        'networks' => ['bnb']
    ],

    // ===== Outros Tokens =====
    'euro-coin' => [
        'symbol' => 'EURC',
        'name' => 'EURC',
        'category' => 'stablecoin',
        'networks' => ['base', 'ethereum', 'polygon', 'arbitrum']
    ],
    'hex' => [
        'symbol' => 'HEX',
        'name' => 'HEX',
        'category' => 'defi',
        'networks' => ['ethereum']
    ]
];

// ============================================================
// LOGGING
// ============================================================

$log_file = dirname(__DIR__) . '/logs/fetch_prices.log';

function log_message($level, $message, $verbose_only = false) {
    global $verbose, $log_file;

    $timestamp = date('Y-m-d H:i:s');
    $log_line = "[$timestamp] [$level] $message\n";

    // Escrever em arquivo (sempre)
    file_put_contents($log_file, $log_line, FILE_APPEND | LOCK_EX);

    // Output CLI (se verbose ou não for verbose_only)
    if (!$verbose_only || $verbose) {
        echo $log_line;
    }
}

// ============================================================
// BANCO DE DADOS
// ============================================================

try {
    $db = Database::getInstance()->getConnection();
    log_message('INFO', 'Conexão com banco estabelecida');
} catch (Exception $e) {
    log_message('CRITICAL', 'Falha na conexão: ' . $e->getMessage());
    exit(1);
}

// ============================================================
// EXECUÇÃO PRINCIPAL
// ============================================================

echo "\n";
echo "╔══════════════════════════════════════════════════════════╗\n";
echo "║         COINGECKO PRICE FETCHER - v2.0                ║\n";
echo "╚══════════════════════════════════════════════════════════╝\n";
echo "\n";

$start_time = microtime(true);
$start_date = date('Y-m-d H:i:s');

log_message('INFO', "Iniciando busca de preços para " . count($TOKENS) . " tokens...");

if ($dry_run) {
    log_message('INFO', '[DRY RUN] Nenhuma atualização será feita no banco');
}

// ============================================================
// PASSO 1: Batch Request CoinGecko (1 chamada para TODOS)
// ============================================================

log_message('INFO', 'Enviando batch request para CoinGecko...');

$batch_result = fetch_batch_prices($coingecko_base_url, $coingecko_api_key, array_keys($TOKENS));

if (!$batch_result) {
    log_message('CRITICAL', 'Falha no batch request. Abortando.');
    exit(1);
}

log_message('INFO', 'Batch request concluído. Processando ' . count($batch_result) . ' tokens...');

// ============================================================
// PASSO 2: Validar e Salvar no Banco
// ============================================================

$stats = [
    'total' => 0,
    'updated' => 0,
    'skipped' => 0,
    'errors' => 0,
    'cached' => 0
];

foreach ($TOKENS as $coingecko_id => $token_info) {
    $stats['total']++;
    $symbol = $token_info['symbol'];

    log_message('INFO', "Processando $symbol ($coingecko_id)...", true);

    // Verificar se recebeu dados
    if (!isset($batch_result[$coingecko_id])) {
        log_message('WARN', "  ⚠ Token $coingecko_id não encontrado na resposta");
        $stats['errors']++;
        continue;
    }

    $price_data = $batch_result[$coingecko_id];

    // Validar dados
    $validation = validate_price_data($price_data, $symbol);
    if (!$validation['valid']) {
        log_message('WARN', "  ⚠ $symbol: " . $validation['reason']);
        $stats['skipped']++;
        continue;
    }

    // Extrair valores
    $price_usd = (float) ($price_data['usd'] ?? 0);
    $price_brl = (float) ($price_data['brl'] ?? 0);
    $change_24h = (float) ($price_data['usd_24h_change'] ?? 0);
    $market_cap = (float) ($price_data['usd_market_cap'] ?? 0);
    $volume_24h = (float) ($price_data['usd_24h_vol'] ?? 0);

    // Dry run mode
    if ($dry_run) {
        log_message('INFO', "  [DRY RUN] USD: $" . number_format($price_usd, 4) . " | BRL: R$ " . number_format($price_brl, 2) . " | 24h: " . number_format($change_24h, 2) . "%");
        $stats['updated']++;
        continue;
    }

    // Verificar se é stablecoin (deve estar próximo de $1)
    if ($token_info['category'] === 'stablecoin') {
        if ($price_usd < 0.95 || $price_usd > 1.05) {
            log_message('WARN', "  ⚠ $symbol: Preço USD fora do esperado para stablecoin: $$price_usd");
        }
    }

    // Salvar no banco
    try {
        $stmt = $db->prepare("
            INSERT INTO token_prices (
                token_symbol,
                token_name,
                coingecko_id,
                price_usd,
                price_brl,
                change_24h,
                market_cap_usd,
                volume_24h,
                source,
                last_updated
            ) VALUES (
                :token_symbol,
                :token_name,
                :coingecko_id,
                :price_usd,
                :price_brl,
                :change_24h,
                :market_cap_usd,
                :volume_24h,
                'coingecko',
                NOW()
            )
            ON DUPLICATE KEY UPDATE
                price_usd = VALUES(price_usd),
                price_brl = VALUES(price_brl),
                change_24h = VALUES(change_24h),
                market_cap_usd = VALUES(market_cap_usd),
                volume_24h = VALUES(volume_24h),
                source = VALUES(source),
                last_updated = NOW()
        ");

        $stmt->execute([
            ':token_symbol' => $symbol,
            ':token_name' => $token_info['name'],
            ':coingecko_id' => $coingecko_id,
            ':price_usd' => $price_usd,
            ':price_brl' => $price_brl,
            ':change_24h' => $change_24h,
            ':market_cap_usd' => $market_cap,
            ':volume_24h' => $volume_24h
        ]);

        log_message('INFO', "  ✓ $symbol: USD $" . number_format($price_usd, 4) . " | BRL R$ " . number_format($price_brl, 2) . " | 24h " . number_format($change_24h, 2) . "%");
        $stats['updated']++;

    } catch (Exception $e) {
        log_message('ERROR', "  ✗ Erro ao salvar $symbol: " . $e->getMessage());
        $stats['errors']++;
    }
}

// ============================================================
// PASSO 2.5: Buscar Tokens Customizados via Yahoo Finance
// ============================================================

$CUSTOM_TOKENS = [
    'TSMon' => ['ticker' => 'TSM', 'name' => 'Taiwan Semiconductor'],
    'ASMLon' => ['ticker' => 'ASML', 'name' => 'ASML Holding NV'],
    'SLVon' => ['ticker' => 'SLV', 'name' => 'iShares Silver Trust']
];

log_message('INFO', "Buscando " . count($CUSTOM_TOKENS) . " tokens customizados (Ondo/Stocks) via Yahoo Finance...");

// Obter cotação do BRL para converter o preço do Yahoo (que vem em USD)
$brlRate = 5.0; // Fallback
try {
    $brlStmt = $db->query("SELECT price_brl / price_usd FROM token_prices WHERE token_symbol = 'USDT' AND price_usd > 0 LIMIT 1");
    $dbRate = $brlStmt->fetchColumn();
    if ($dbRate) $brlRate = (float)$dbRate;
} catch (Exception $e) {}

foreach ($CUSTOM_TOKENS as $symbol => $data) {
    $stats['total']++;
    $ticker = $data['ticker'];
    log_message('INFO', "Processando $symbol ($ticker via Yahoo Finance)...", true);

    $url = "https://query1.finance.yahoo.com/v8/finance/chart/{$ticker}?interval=1d&range=1d";
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_HTTPHEADER => ['User-Agent: Mozilla/5.0']
    ]);
    $response = curl_exec($ch);
    curl_close($ch);

    if ($response) {
        $json = json_decode($response, true);
        if (isset($json['chart']['result'][0]['meta']['regularMarketPrice'])) {
            $price_usd = (float)$json['chart']['result'][0]['meta']['regularMarketPrice'];
            $price_brl = $price_usd * $brlRate;
            // Pegar variação se disponível
            $prevClose = (float)($json['chart']['result'][0]['meta']['chartPreviousClose'] ?? $price_usd);
            $change_24h = $prevClose > 0 ? (($price_usd - $prevClose) / $prevClose) * 100 : 0;

            if ($dry_run) {
                log_message('INFO', "  [DRY RUN] USD: $" . number_format($price_usd, 4) . " | BRL: R$ " . number_format($price_brl, 2) . " | 24h: " . number_format($change_24h, 2) . "%");
                $stats['updated']++;
                continue;
            }

            try {
                $stmt = $db->prepare("
                    INSERT INTO token_prices (
                        token_symbol, token_name, coingecko_id, price_usd, price_brl, change_24h, source, last_updated
                    ) VALUES (
                        :symbol, :name, 'yahoo_finance', :price_usd, :price_brl, :change_24h, 'yahoo', NOW()
                    ) ON DUPLICATE KEY UPDATE
                        price_usd = :price_usd,
                        price_brl = :price_brl,
                        change_24h = :change_24h,
                        source = 'yahoo',
                        last_updated = NOW()
                ");
                $stmt->execute([
                    ':symbol' => $symbol,
                    ':name' => $data['name'],
                    ':price_usd' => $price_usd,
                    ':price_brl' => $price_brl,
                    ':change_24h' => $change_24h
                ]);
                log_message('INFO', "  ✓ $symbol: USD $" . number_format($price_usd, 4) . " | BRL R$ " . number_format($price_brl, 2) . " | 24h " . number_format($change_24h, 2) . "%");
                $stats['updated']++;
            } catch (Exception $e) {
                log_message('ERROR', "  ✗ Erro ao salvar $symbol: " . $e->getMessage());
                $stats['errors']++;
            }
        } else {
            log_message('WARN', "  ⚠ Dados inválidos para $ticker no Yahoo Finance");
            $stats['errors']++;
        }
    } else {
        log_message('WARN', "  ⚠ Falha de conexão com Yahoo Finance para $ticker");
        $stats['errors']++;
    }
}

// ============================================================
// PASSO 3: Relatório Final
// ============================================================

$duration = microtime(true) - $start_time;

echo "\n";
echo "╔══════════════════════════════════════════════════════════╗\n";
echo "║                  RELATÓRIO FINAL                       ║\n";
echo "╚══════════════════════════════════════════════════════════╝\n";
echo "\n";

echo "  Início:       $start_date\n";
echo "  Duração:      " . number_format($duration, 2) . "s\n";
echo "  Tokens total:  {$stats['total']}\n";
echo "  ✓ Atualizados: {$stats['updated']}\n";
echo "  ⚠ Pulados:     {$stats['skipped']}\n";
echo "  ✗ Erros:       {$stats['errors']}\n";
echo "\n";

if ($stats['errors'] > 0) {
    log_message('WARN', "Atenção: {$stats['errors']} tokens com erros. Verifique o log: $log_file");
}

if ($stats['updated'] === 0 && $stats['errors'] > 0) {
    log_message('CRITICAL', 'Nenhum token atualizado! Verifique a API key e conectividade.');
    exit(1);
}

// Taxa de sucesso
$success_rate = $stats['total'] > 0 ? ($stats['updated'] / $stats['total']) * 100 : 0;
echo "  Taxa de sucesso: " . number_format($success_rate, 1) . "%\n";

// Verificar se precisa alertar sobre falhas consecutivas
if ($success_rate < 50) {
    log_message('CRITICAL', "Taxa de sucesso crítica: " . number_format($success_rate, 1) . "%. Verifique a API CoinGecko.");

    // Salvar alerta em tabela de logs (se existir)
    try {
        $db->prepare("
            INSERT INTO sync_logs (wallet_id, status, message, executed_at)
            VALUES (0, 'error', 'Fetch prices: taxa de sucesso crítica (' . $success_rate . '%)', NOW())
        ")->execute();
    } catch (Exception $e) {
        // Tabela pode não existir, ignorar
    }
}

log_message('INFO', 'Worker concluído com sucesso!');

// ============================================================
// PASSO 4: Acumular snapshot diário em price_history
// ============================================================

log_message('INFO', 'Salvando snapshot diário em price_history...');
$history_count = 0;

try {
    $historyStmt = $db->prepare("
        INSERT IGNORE INTO price_history (token_symbol, price_usd, price_brl, recorded_at, source)
        SELECT token_symbol, price_usd, price_brl, NOW(), 'coingecko'
        FROM token_prices
        WHERE price_usd > 0
        AND DATE(last_updated) = CURDATE()
    ");
    $historyStmt->execute();
    $history_count = $historyStmt->rowCount();
    log_message('INFO', "  ✓ Snapshots inseridos em price_history: $history_count");
} catch (Exception $e) {
    log_message('WARN', "  ⚠ Erro ao salvar price_history: " . $e->getMessage());
}

// ============================================================
// PASSO 5: Atualizar balance_usd em wallet_balances
// ============================================================

log_message('INFO', 'Atualizando balance_usd em wallet_balances...');

try {
    $balanceStmt = $db->query("
        UPDATE wallet_balances wb
        JOIN token_prices tp ON wb.token_symbol = tp.token_symbol
        SET wb.balance_usd = wb.balance * tp.price_usd
        WHERE tp.price_usd > 0
    ");
    $updated_balances = $balanceStmt->rowCount();
    log_message('INFO', "  ✓ Saldos USD atualizados: $updated_balances registros");
} catch (Exception $e) {
    log_message('WARN', "  ⚠ Erro ao atualizar balance_usd: " . $e->getMessage());
}

// ============================================================
// PASSO 6: Acumular snapshot de patrimônio por usuário em portfolio_history
// ============================================================

log_message('INFO', 'Salvando snapshot de patrimônio por usuário...');

try {
    $portfolioStmt = $db->query("
        INSERT INTO portfolio_history (user_id, date, total_value_usd, total_value_brl)
        SELECT w.user_id, CURDATE(),
               COALESCE(SUM(wb.balance_usd), 0),
               COALESCE(SUM(wb.balance_usd), 0) * COALESCE(
                   (SELECT tp.price_brl / tp.price_usd FROM token_prices tp WHERE tp.token_symbol = 'ETH' AND tp.price_usd > 0 LIMIT 1),
                   5.0
               )
        FROM wallets w
        LEFT JOIN wallet_balances wb ON w.id = wb.wallet_id
        WHERE w.is_active = 1
        GROUP BY w.user_id
        ON DUPLICATE KEY UPDATE
            total_value_usd = VALUES(total_value_usd),
            total_value_brl = VALUES(total_value_brl)
    ");
    log_message('INFO', "  ✓ Portfolio history atualizado: " . $portfolioStmt->rowCount() . " usuários");
} catch (Exception $e) {
    log_message('WARN', "  ⚠ Erro ao salvar portfolio_history: " . $e->getMessage());
}

echo "\n";

exit(0);

// ============================================================
// FUNÇÕES AUXILIARES
// ============================================================

/**
 * Batch Request - Busca preços de TODOS os tokens em 1 chamada
 *
 * Economia: 8 tokens = 1 chamada ao invés de 8 (87% de economia)
 * Rate limit CoinGecko Pro: 100 req/min (suficiente para 1 req a cada 15 min)
 */
function fetch_batch_prices($base_url, $api_key, $token_ids) {
    $ids = implode(',', $token_ids);

    $url = $base_url . '/simple/price';
    $params = http_build_query([
        'ids' => $ids,
        'vs_currencies' => 'usd,brl',
        'include_24hr_change' => 'true',
        'include_market_cap' => 'true',
        'include_24hr_vol' => 'true'
    ]);

    $full_url = "$url?$params";

    log_message('INFO', "URL: $full_url", true);

    $max_retries = 3;
    $retry_delay = 2; // segundos

    for ($attempt = 1; $attempt <= $max_retries; $attempt++) {
        log_message('INFO', "  Tentativa $attempt/$max_retries...", true);

        $ch = curl_init($full_url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'x-cg-demo-api-key: ' . $api_key  // API gratuita (Demo)
            ],
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_ENCODING => ''
        ]);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);

        // Erro de conexão
        if ($curl_error) {
            log_message('WARN', "  Curl error: $curl_error");
            if ($attempt < $max_retries) {
                sleep($retry_delay);
                $retry_delay *= 2; // Backoff exponencial
                continue;
            }
            log_message('ERROR', "Falha após $max_retries tentativas");
            return false;
        }

        // Rate limit (429)
        if ($http_code === 429) {
            $retry_after = 60;
            log_message('WARN', "  Rate limit (429). Aguardando ${retry_after}s...");
            sleep($retry_after);
            continue;
        }

        // Erro HTTP
        if ($http_code !== 200) {
            log_message('WARN', "  HTTP $http_code: " . substr($response, 0, 200));
            if ($attempt < $max_retries) {
                sleep($retry_delay);
                $retry_delay *= 2;
                continue;
            }
            return false;
        }

        // Parse JSON
        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            log_message('ERROR', "JSON parse error: " . json_last_error_msg());
            return false;
        }

        // CoinGecko retorna objeto com IDs como keys
        if (!is_array($data) || empty($data)) {
            log_message('WARN', "Resposta vazia da CoinGecko");
            return false;
        }

        log_message('INFO', "  ✓ Recebido dados para " . count($data) . " tokens");
        return $data;
    }

    return false;
}

/**
 * Valida dados de preço recebidas
 */
function validate_price_data($price_data, $symbol) {
    // Deve ter pelo menos USD
    if (!isset($price_data['usd'])) {
        return ['valid' => false, 'reason' => 'Preço USD não recebido'];
    }

    $usd = (float) $price_data['usd'];

    // Preço zero (pode ser erro da API)
    if ($usd === 0.0) {
        return ['valid' => false, 'reason' => 'Preço USD zerado'];
    }

    // Preço negativo (impossível)
    if ($usd < 0) {
        return ['valid' => false, 'reason' => "Preço negativo: $$usd"];
    }

    // Outlier: preço > 1 trilhão (provável erro)
    if ($usd > 1000000000000) {
        return ['valid' => false, 'reason' => "Preço outlier: $" . number_format($usd)];
    }

    // Stablecoins: verificar se está perto de $1
    if (in_array($symbol, ['USDT', 'USDC', 'DAI', 'BUSD'])) {
        if ($usd < 0.80 || $usd > 1.20) {
            return ['valid' => false, 'reason' => "Stablecoin fora do range: $$usd"];
        }
    }

    return ['valid' => true, 'reason' => 'OK'];
}
