<?php
/**
 * Worker: Fetch Balances (Busca saldos atuais direto da blockchain)
 * ============================================================
 * Busca saldos de TODAS as carteiras ativas via Alchemy API
 * e armazena em wallet_balances para uso no dashboard/assets.
 *
 * Uso via cron:
 * a cada 15 minutos: php workers/fetch_balances.php
 *
 * Uso manual:
 * php workers/fetch_balances.php --verbose
 */

require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/src/Utils/WeiConverter.php';
require_once dirname(__DIR__) . '/src/Blockchain/NetworkConfig.php';
require_once dirname(__DIR__) . '/src/Blockchain/AlchemyClient.php';

// Inicializar Database para carregar .env
Database::getInstance();

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die('CLI only');
}

$verbose = in_array('--verbose', $_SERVER['argv']);

echo "\n╔══════════════════════════════════════════════════════════╗\n";
echo "║         BALANCE FETCHER - Blockchain Live              ║\n";
echo "╚══════════════════════════════════════════════════════════╝\n\n";

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    echo "[ERRO] Banco de dados: " . $e->getMessage() . "\n";
    exit(1);
}

// Carregar chaves Alchemy do .env
$env_file = dirname(__DIR__) . '/.env';
$env_content = file_get_contents($env_file);
$alchemy_keys = [];
foreach (['ETHEREUM', 'BNB', 'ARBITRUM', 'BASE', 'POLYGON'] as $net) {
    if (preg_match("/^ALCHEMY_{$net}_KEY=(.+)$/m", $env_content, $matches)) {
        $alchemy_keys[strtolower($net)] = trim($matches[1]);
    }
}

// Buscar todas as carteiras ativas
$stmt = $db->query("SELECT w.*, u.id as user_id FROM wallets w JOIN users u ON w.user_id = u.id WHERE w.is_active = 1 ORDER BY w.id");
$wallets = $stmt->fetchAll();

echo "Carteiras ativas: " . count($wallets) . "\n\n";

$stats = ['wallets' => 0, 'tokens' => 0, 'errors' => 0];

foreach ($wallets as $wallet) {
    $network = $wallet['network'];
    $address = $wallet['address'];
    $apiKey = $alchemy_keys[$network] ?? null;
    
    if (!$apiKey) {
        echo "[WARN] Sem chave Alchemy para $network - pulando wallet #{$wallet['id']}\n";
        $stats['errors']++;
        continue;
    }
    
    echo "[INFO] Wallet #{$wallet['id']} ($network): $address\n";
    
    try {
        $alchemy = new AlchemyClient($network, $apiKey);
        
        // 1. Buscar saldo nativo (ETH, BNB, etc.)
        $nativeBalanceHex = $alchemy->getNativeBalance($address);
        $nativeBalanceWei = hexdec($nativeBalanceHex);
        $nativeSymbol = $alchemy->getNativeSymbol();
        $nativeDecimals = NetworkConfig::getDecimals($network);
        $nativeBalance = WeiConverter::weiToDecimal($nativeBalanceWei, $nativeDecimals);
        
        if ($nativeBalance > 0) {
            $stmt = $db->prepare("
                INSERT INTO wallet_balances (wallet_id, token_address, token_symbol, token_name, balance, last_updated)
                VALUES (?, NULL, ?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE 
                    balance = VALUES(balance),
                    last_updated = NOW()
            ");
            $stmt->execute([$wallet['id'], $nativeSymbol, $nativeSymbol . ' Native', $nativeBalance]);
            echo "  ✓ $nativeSymbol: $nativeBalance\n";
            $stats['tokens']++;
        }
        
        // 2. Buscar saldos de tokens ERC-20
        $tokenBalances = $alchemy->getTokenBalances($address);
        
        foreach ($tokenBalances as $tokenData) {
            $tokenAddress = $tokenData['contractAddress'] ?? null;
            $balanceHex = $tokenData['tokenBalance'] ?? '0x0';
            
            // GMP para conversão precisa (evita float/scientific notation)
            $balanceHexClean = ltrim(str_replace('0x', '', $balanceHex), '0');
            if (empty($balanceHexClean)) continue;
            $balanceWei = gmp_strval(gmp_init($balanceHexClean, 16));
            
            // Tokens conhecidos por rede (com decimais corretos)
            $knownTokens = [
                // Ethereum Mainnet
                '0xdac17f958d2ee523a2206206994597c13d831ec7' => ['symbol' => 'USDT', 'name' => 'Tether USD', 'decimals' => 6],
                '0xa0b86991c6218b36c1d19d4a2e9eb0ce3606eb48' => ['symbol' => 'USDC', 'name' => 'USD Coin', 'decimals' => 6],
                '0x2260fac5e5542a773aa44fbcfedf7c193bc2c599' => ['symbol' => 'WBTC', 'name' => 'Wrapped Bitcoin', 'decimals' => 8],
                '0x514910771af9ca656af840dff83e8264ecf986ca' => ['symbol' => 'LINK', 'name' => 'Chainlink', 'decimals' => 18],
                '0x7fc66500c84a76ad7e9c93437bfc5ac33e2ddae9' => ['symbol' => 'AAVE', 'name' => 'Aave', 'decimals' => 18],
                '0x1f9840a85d5af5bf1d1762f925bdaddc4201f984' => ['symbol' => 'UNI', 'name' => 'Uniswap', 'decimals' => 18],
                // BNB Chain
                '0x55d398326f99059ff775485246999027b3197955' => ['symbol' => 'USDT', 'name' => 'Tether USD (BSC)', 'decimals' => 18],
                '0x8ac76a51cc950d9822d68b83fe1ad97b32cd580d' => ['symbol' => 'USDC', 'name' => 'USD Coin (BSC)', 'decimals' => 18],
                '0x7130d2a12b9bcbfae4f2634d864a1ee1ce3ead9c' => ['symbol' => 'BTCB', 'name' => 'BTCB', 'decimals' => 18],
                '0xbb4cdb9cbd36b01bd1cbaebf2de08d9173bc095c' => ['symbol' => 'WBNB', 'name' => 'Wrapped BNB', 'decimals' => 18],
                '0x0e09fabb73bd3ade0a17ecc321fd13a19e81ce82' => ['symbol' => 'CAKE', 'name' => 'PancakeSwap', 'decimals' => 18],
                // Base
                '0x833589fcd6edb6e08f4c7c32d4f71b54bda02913' => ['symbol' => 'USDC', 'name' => 'USD Coin (Base)', 'decimals' => 6],
                '0x4200000000000000000000000000000000000006' => ['symbol' => 'WETH', 'name' => 'Wrapped ETH (Base)', 'decimals' => 18],
            ];
            
            $lowerAddress = strtolower($tokenAddress);
            if (isset($knownTokens[$lowerAddress])) {
                $info = $knownTokens[$lowerAddress];
            } else {
                // Buscar da API
                $info = $alchemy->getTokenInfo($tokenAddress);
            }
            
            $symbol = $info['symbol'] ?? null;
            $name = $info['name'] ?? '';
            $decimals = $info['decimals'] ?? 18;
            
            // Filtrar tokens sem símbolo válido
            if (empty($symbol) || $symbol === 'UNKNOWN' || strlen($symbol) > 10) {
                continue;
            }
            
            // Filtrar tokens spam: símbolo deve ser apenas letras e números (3-8 chars)
            if (!preg_match('/^[A-Za-z0-9]{3,8}$/', $symbol)) {
                continue;
            }
            
            // Ignorar tokens que parecem spam/scam (nomes com URLs, claims, etc.)
            if (stripos($name, 'http') !== false || stripos($name, 't.me') !== false || stripos($name, 'telegram') !== false || stripos($name, 'claim') !== false || stripos($name, 'visit') !== false || stripos($name, 'soon') !== false) {
                continue;
            }
            
            // Blacklist de tokens scam (EURC removido - stablecoin legítima)
            $spamSymbols = ['TGE','PEPA','COCO','BUC','JUSDC','BTW','DLM','FACE','GOON','PF','SWF','CHOG','ELSA','ZAMA','GPT5','KIMI','DOWNALD','CMK','CRC','OBX','WKEYDAO','WLSNBCK','ZPT','TYB','ANOME','ABY','AEM','AFG','VSP','DEUS','B2','AGU','BZW','ACU','BTCF','CBTC','VEREM','NVDA','OPENAI','GITHUB','GPT','LMC','CGX','TAPZI','USDF','AI'];
            if (in_array(strtoupper(trim($symbol)), $spamSymbols)) {
                continue;
            }
            
            // Ignorar tokens de dívida (empréstimos)
            if (stripos($symbol, 'DEBT') !== false || stripos($symbol, 'VARIABLE') !== false) {
                continue;
            }
            
            // Converter balance corretamente com decimais do token
            $balance = WeiConverter::weiToDecimal($balanceWei, $decimals);
            
            // Ignorar balances absurdamente pequenas (< 0.000001)
            if ($balance < 0.000001) continue;
            
            // Ignorar balances absurdamente grandes (> 1 bilhão - provável erro)
            if ($balance > 1000000000) {
                echo "  ⚠ Token ignorado (balance absurdo): $symbol = $balance\n";
                continue;
            }
            
            $stmt = $db->prepare("
                INSERT INTO wallet_balances (wallet_id, token_address, token_symbol, token_name, balance, last_updated)
                VALUES (?, ?, ?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE 
                    balance = VALUES(balance),
                    token_name = VALUES(token_name),
                    last_updated = NOW()
            ");
            $stmt->execute([$wallet['id'], $tokenAddress, $symbol, $name, $balance]);
            echo "  ✓ $symbol: $balance\n";
            $stats['tokens']++;
        }
        
        $stats['wallets']++;
        
    } catch (Exception $e) {
        echo "  ✗ Erro: " . $e->getMessage() . "\n";
        $stats['errors']++;
    }
    
    echo "\n";
}

// Relatório final
echo "╔══════════════════════════════════════════════════════════╗\n";
echo "║                  RELATÓRIO FINAL                       ║\n";
echo "╚══════════════════════════════════════════════════════════╝\n\n";
echo "  Carteiras:  {$stats['wallets']}\n";
echo "  Tokens:     {$stats['tokens']}\n";
echo "  Erros:      {$stats['errors']}\n\n";

// Atualizar balance_usd com base nos preços atuais
echo "[INFO] Atualizando balance_usd com preços atuais...\n";
$stmt = $db->query("
    UPDATE wallet_balances wb
    JOIN token_prices tp ON wb.token_symbol = tp.token_symbol
    SET wb.balance_usd = wb.balance * tp.price_usd
    WHERE wb.balance_usd IS NULL OR wb.balance_usd = 0
");
echo "  ✓ Atualizados: " . $stmt->rowCount() . " registros\n\n";

echo "Concluído!\n";
exit(0);
