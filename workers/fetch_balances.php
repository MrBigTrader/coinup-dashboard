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
            $balanceHex = $tokenData['balance'] ?? '0x0';
            $balanceWei = hexdec($balanceHex);
            
            if ($balanceWei === 0) continue; // Ignorar tokens com saldo 0
            
            // Buscar info do token (symbol, name, decimals)
            // Para tokens conhecidos, usar cache para evitar chamadas extras
            $knownTokens = [
                '0xdac17f958d2ee523a2206206994597c13d831ec7' => ['symbol' => 'USDT', 'name' => 'Tether USD', 'decimals' => 6],
                '0xa0b86991c6218b36c1d19d4a2e9eb0ce3606eb48' => ['symbol' => 'USDC', 'name' => 'USD Coin', 'decimals' => 6],
                '0x2260fac5e5542a773aa44fbcfedf7c193bc2c599' => ['symbol' => 'WBTC', 'name' => 'Wrapped Bitcoin', 'decimals' => 8],
            ];
            
            $lowerAddress = strtolower($tokenAddress);
            if (isset($knownTokens[$lowerAddress])) {
                $info = $knownTokens[$lowerAddress];
            } else {
                // Buscar da API
                $info = $alchemy->getTokenInfo($tokenAddress);
            }
            
            $symbol = $info['symbol'] ?? 'UNKNOWN';
            $name = $info['name'] ?? $symbol;
            $decimals = $info['decimals'] ?? 18;
            
            // Se o símbolo for vazio ou "UNKNOWN", pular
            if (empty($symbol) || $symbol === 'UNKNOWN') {
                echo "  ⚠ Token sem símbolo ($tokenAddress) - pulando\n";
                continue;
            }
            
            $balance = WeiConverter::weiToDecimal($balanceWei, $decimals);
            
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
