<?php
/**
 * AlchemyClient - Cliente para API Alchemy
 * Revisão: 2026-04-06-FinalFix
 * Descrição: Gerencia conexões com as 5 redes EVM via Alchemy API.
 * Implementa cache incremental por bloco e busca otimizada de primeiro bloco.
 */

class AlchemyClient {

    /** @var string Chave API da rede atual */
    private $apiKey;

    /** @var string Rede atual (ethereum, bnb, arbitrum, base, polygon) */
    private $network;

    /** @var string URL base da API Alchemy para a rede */
    private $baseUrl;

    /** @var array Cache de configurações por rede */
    private const API_URLS = [
        'ethereum' => 'https://eth-mainnet.g.alchemy.com/v2/',
        'bnb' => 'https://bnb-mainnet.g.alchemy.com/v2/',
        'arbitrum' => 'https://arb-mainnet.g.alchemy.com/v2/',
        'base' => 'https://base-mainnet.g.alchemy.com/v2/',
        'polygon' => 'https://polygon-mainnet.g.alchemy.com/v2/',
    ];

    /** @var int Timeout padrão em segundos */
    private const TIMEOUT = 30;

    /** @var int Número máximo de retries */
    private const MAX_RETRIES = 3;

    /** @var int Delay entre retries em ms */
    private const RETRY_DELAY = 1000;

    /**
     * Construtor
     */
    public function __construct(string $network, string $apiKey) {
        if (!NetworkConfig::isSupported($network)) {
            throw new InvalidArgumentException("Rede não suportada: $network");
        }
        $this->network = $network;
        $this->apiKey = $apiKey;
        $this->baseUrl = self::API_URLS[$network];
    }

    /**
     * Obter bloco atual da rede
     */
    public function getCurrentBlock(): int {
        $payload = ['jsonrpc' => '2.0', 'method' => 'eth_blockNumber', 'params' => [], 'id' => 1];
        $response = $this->request($payload);
        return hexdec($response['result']);
    }

    /**
     * Obter transações nativas
     */
    public function getNativeTransfers(string $address, int $fromBlock, int $toBlock): array {
        $allTransfers = [];
        $payloadFrom = [
            'jsonrpc' => '2.0', 'method' => 'alchemy_getAssetTransfers', 'params' => [[
                'fromBlock' => '0x' . dechex($fromBlock), 'toBlock' => '0x' . dechex($toBlock),
                'fromAddress' => $address, 'category' => ['external'], 'maxCount' => '0x3e8', 'excludeZeroValue' => true,
            ]], 'id' => 1
        ];
        $resFrom = $this->request($payloadFrom);
        $allTransfers = array_merge($allTransfers, $resFrom['result']['transfers'] ?? []);

        $payloadTo = [
            'jsonrpc' => '2.0', 'method' => 'alchemy_getAssetTransfers', 'params' => [[
                'fromBlock' => '0x' . dechex($fromBlock), 'toBlock' => '0x' . dechex($toBlock),
                'toAddress' => $address, 'category' => ['external'], 'maxCount' => '0x3e8', 'excludeZeroValue' => true,
            ]], 'id' => 2
        ];
        $resTo = $this->request($payloadTo);
        $allTransfers = array_merge($allTransfers, $resTo['result']['transfers'] ?? []);

        $unique = [];
        foreach ($allTransfers as $tx) {
            $hash = $tx['hash'] ?? '';
            if ($hash && !isset($unique[$hash])) $unique[$hash] = $tx;
        }
        return array_values($unique);
    }

    /**
     * Obter transfers de tokens ERC-20
     */
    public function getTokenTransfers(string $address, int $fromBlock, int $toBlock): array {
        $allTransfers = [];
        $payloadFrom = [
            'jsonrpc' => '2.0', 'method' => 'alchemy_getAssetTransfers', 'params' => [[
                'fromBlock' => '0x' . dechex($fromBlock), 'toBlock' => '0x' . dechex($toBlock),
                'fromAddress' => $address, 'category' => ['erc20'], 'maxCount' => '0x3e8', 'excludeZeroValue' => true,
            ]], 'id' => 1
        ];
        $resFrom = $this->request($payloadFrom);
        $allTransfers = array_merge($allTransfers, $resFrom['result']['transfers'] ?? []);

        $payloadTo = [
            'jsonrpc' => '2.0', 'method' => 'alchemy_getAssetTransfers', 'params' => [[
                'fromBlock' => '0x' . dechex($fromBlock), 'toBlock' => '0x' . dechex($toBlock),
                'toAddress' => $address, 'category' => ['erc20'], 'maxCount' => '0x3e8', 'excludeZeroValue' => true,
            ]], 'id' => 2
        ];
        $resTo = $this->request($payloadTo);
        $allTransfers = array_merge($allTransfers, $resTo['result']['transfers'] ?? []);

        $unique = [];
        foreach ($allTransfers as $tx) {
            $hash = $tx['hash'] ?? '';
            if ($hash && !isset($unique[$hash])) $unique[$hash] = $tx;
        }
        return array_values($unique);
    }

    /**
     * Obter todas as transações (nativo + tokens)
     */
    public function getAllTransfers(string $address, int $fromBlock, int $toBlock): array {
        $allTransfers = [];
        $native = $this->getNativeTransfers($address, $fromBlock, $toBlock);
        foreach ($native as $tx) { $tx['_transferType'] = 'native'; $allTransfers[] = $tx; }
        
        $tokens = $this->getTokenTransfers($address, $fromBlock, $toBlock);
        foreach ($tokens as $tx) { $tx['_transferType'] = 'erc20'; $allTransfers[] = $tx; }
        
        return $allTransfers;
    }

    /**
     * Obter saldo nativo
     */
    public function getBalance(string $address): string {
        $payload = ['jsonrpc' => '2.0', 'method' => 'eth_getBalance', 'params' => [$address, 'latest'], 'id' => 1];
        return $this->request($payload)['result'];
    }

    /**
     * Obter info de token
     */
    public function getTokenInfo(string $tokenAddress): array {
        $info = ['address' => $tokenAddress, 'name' => null, 'symbol' => null, 'decimals' => 18];
        
        $name = $this->callContract($tokenAddress, '0x06fdde03');
        if ($name && $name !== '0x') $info['name'] = $this->decodeString($name);
        
        $sym = $this->callContract($tokenAddress, '0x95d89b41');
        if ($sym && $sym !== '0x') $info['symbol'] = $this->decodeString($sym);
        
        $dec = $this->callContract($tokenAddress, '0x313ce567');
        if ($dec && $dec !== '0x') $info['decimals'] = hexdec($dec);
        
        return $info;
    }

    private function callContract(string $to, string $data): ?string {
        $res = $this->request(['jsonrpc' => '2.0', 'method' => 'eth_call', 'params' => [['to' => $to, 'data' => $data], 'latest'], 'id' => 1]);
        return $res['result'] ?? null;
    }

    private function decodeString(string $hexData): string {
        $hexData = str_replace('0x', '', $hexData);
        if (strlen($hexData) < 128) return '';
        $length = hexdec(substr($hexData, 64, 64));
        return hex2bin(substr($hexData, 128, $length * 2));
    }

    private function request(array $payload, int $retry = 0): array {
        $url = $this->baseUrl . $this->apiKey;
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT => self::TIMEOUT, CURLOPT_CONNECTTIMEOUT => 10,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error || !$response) {
            if ($retry < self::MAX_RETRIES) { usleep(self::RETRY_DELAY * 1000 * ($retry + 1)); return $this->request($payload, $retry + 1); }
            throw new Exception("Erro conexão Alchemy: $error");
        }
        if ($httpCode !== 200) {
            if ($retry < self::MAX_RETRIES) { usleep(self::RETRY_DELAY * 1000 * ($retry + 1)); return $this->request($payload, $retry + 1); }
            throw new Exception("Alchemy HTTP $httpCode");
        }
        $data = json_decode($response, true);
        if (isset($data['error'])) {
            $msg = $data['error']['message'] ?? 'Erro';
            if (strpos($msg, 'rate limit') !== false && $retry < self::MAX_RETRIES) {
                usleep(5000 * 1000 * ($retry + 1)); return $this->request($payload, $retry + 1);
            }
            throw new Exception("Alchemy API Error: $msg");
        }
        return $data;
    }

    public function getNetwork(): string { return $this->network; }
    public function getNativeSymbol(): string { return NetworkConfig::getSymbol($this->network) ?? 'ETH'; }

    /**
     * Encontrar primeiro bloco com atividade nativa (REV: 20260406-Final)
     * Usa Alchemy com order=asc para buscar a transação mais antiga.
     */
    public function findFirstBlock(string $address): ?int {
        $payloadFrom = ['jsonrpc' => '2.0', 'method' => 'alchemy_getAssetTransfers', 'params' => [[
            'fromBlock' => '0x0', 'toBlock' => 'latest', 'fromAddress' => $address,
            'category' => ['external'], 'maxCount' => '0x1', 'order' => 'asc', 'excludeZeroValue' => true,
        ]], 'id' => 100];
        $resFrom = $this->request($payloadFrom);
        $transfers = $resFrom['result']['transfers'] ?? [];

        $payloadTo = ['jsonrpc' => '2.0', 'method' => 'alchemy_getAssetTransfers', 'params' => [[
            'fromBlock' => '0x0', 'toBlock' => 'latest', 'toAddress' => $address,
            'category' => ['external'], 'maxCount' => '0x1', 'order' => 'asc', 'excludeZeroValue' => true,
        ]], 'id' => 101];
        $resTo = $this->request($payloadTo);
        $transfers = array_merge($transfers, $resTo['result']['transfers'] ?? []);

        if (empty($transfers)) return null;
        $minBlock = null;
        foreach ($transfers as $tx) {
            if (isset($tx['blockNum'])) {
                $block = hexdec($tx['blockNum']);
                if ($minBlock === null || $block < $minBlock) $minBlock = $block;
            }
        }
        return $minBlock;
    }

    /**
     * Encontrar primeiro bloco com transferência de token (REV: 20260406-Final)
     */
    public function findFirstTokenBlock(string $address): ?int {
        $payloadFrom = ['jsonrpc' => '2.0', 'method' => 'alchemy_getAssetTransfers', 'params' => [[
            'fromBlock' => '0x0', 'toBlock' => 'latest', 'fromAddress' => $address,
            'category' => ['erc20'], 'maxCount' => '0x1', 'order' => 'asc', 'excludeZeroValue' => true,
        ]], 'id' => 102];
        $resFrom = $this->request($payloadFrom);
        $transfers = $resFrom['result']['transfers'] ?? [];

        $payloadTo = ['jsonrpc' => '2.0', 'method' => 'alchemy_getAssetTransfers', 'params' => [[
            'fromBlock' => '0x0', 'toBlock' => 'latest', 'toAddress' => $address,
            'category' => ['erc20'], 'maxCount' => '0x1', 'order' => 'asc', 'excludeZeroValue' => true,
        ]], 'id' => 103];
        $resTo = $this->request($payloadTo);
        $transfers = array_merge($transfers, $resTo['result']['transfers'] ?? []);

        if (empty($transfers)) return null;
        $minBlock = null;
        foreach ($transfers as $tx) {
            if (isset($tx['blockNum'])) {
                $block = hexdec($tx['blockNum']);
                if ($minBlock === null || $block < $minBlock) $minBlock = $block;
            }
        }
        return $minBlock;
    }

    public function findFirstActivityBlock(string $address): int {
        $native = $this->findFirstBlock($address);
        $token = $this->findFirstTokenBlock($address);
        $blocks = array_filter([$native, $token], fn($b) => $b !== null);
        return !empty($blocks) ? min($blocks) : 0;
    }
}
?>